<?php

namespace App\Services\V1\Auth;

use App\Models\AuthRule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuthorizationResolver
{
    /**
     * Main entry point.
     *
     * Mental model:
     *  - store_scope_mode = "none"       → check ONLY global (Spatie) roles & permissions
     *  - store_scope_mode = "scoped"     → check ONLY roles & permissions the user holds FOR that store
     *  - store_scope_mode = "all_stores" → user must have access to every active store, then check global perms
     *
     * A permission granted only inside a store does NOT satisfy a global check, and vice versa.
     */
    public function check(
        string $service,
        string $method,
        string $path,
        ?string $routeName,
        array $userRolesGlobal,   // Spatie global roles (passed in but re-fetched below for safety)
        array $userPermsGlobal,   // Spatie global permissions
        array $tokenAbilities,
        array $storeContext,
        int $userId
    ): array {
        // ── 1) Super-role bypass ──────────────────────────────────────────────
        $superRoles = (array) config('authz.super_roles', []);
        $user = User::find($userId);

        if (!$user) {
            return [false, [], 'user-not-found', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // Always use fresh DB roles (avoids stale data from caller)
        $userRolesGlobal = $user->roles->pluck('name')->toArray();
        $userPermsGlobal = $user->getAllPermissions()->pluck('name')->toArray();

        if ($this->hasAny($userRolesGlobal, $superRoles)) {
            return [true, [], 'super-role', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // ── 2) Load cached rules ──────────────────────────────────────────────
        $ver = (int) Cache::get('authz:ver', 1);
        $rules = $this->getRulesCached($service, $method, $ver);

        // ── 3) Match rule: routeName first, then path_regex ───────────────────
        $matched = null;

        if ($routeName) {
            foreach ($rules as $rule) {
                if (($rule['route_name'] ?? null) === $routeName) {
                    $matched = $rule;
                    break;
                }
            }
        }

        if (!$matched) {
            foreach ($rules as $rule) {
                if (!empty($rule['path_regex']) && preg_match($rule['path_regex'], $path)) {
                    $matched = $rule;
                    break;
                }
            }
        }

        if (!$matched) {
            $allowIfNoRule = (bool) config('authz.allow_if_no_rule', false);
            return [
                $allowIfNoRule,
                [],
                $allowIfNoRule ? 'no-rule-allowed' : 'no-rule-denied',
                ['store_ids' => [], 'store_mode' => 'none'],
            ];
        }

        // ── 4) Delegate to evaluateRule() ─────────────────────────────────────
        return $this->evaluateRule(
            $matched,
            $userRolesGlobal,
            $userPermsGlobal,
            $tokenAbilities,
            $storeContext,
            $userId
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RULE EVALUATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate a matched rule against the user's credentials.
     *
     * Handles all three store_scope_mode values:
     *   none       → global roles/permissions only
     *   scoped     → per-store roles/permissions only
     *   all_stores → user must cover all active stores, then global perms apply
     */
    private function evaluateRule(
        array $rule,
        array $userRolesGlobal,
        array $userPermsGlobal,
        array $tokenAbilities,
        array $storeContext,
        int $userId
    ): array {
        $storeMode = (string) ($rule['store_scope_mode'] ?? 'none');

        // ── Global roles_any bypass (works for ALL modes) ─────────────────────
        // These are "super" roles at the rule level (e.g. admin can do anything)
        $rolesAny = (array) ($rule['roles_any'] ?? []);
        if (!empty($rolesAny) && $this->hasAny($userRolesGlobal, $rolesAny)) {
            $storeIds = $this->extractStoreIds($rule, $storeContext);
            return [true, [], 'roles_any', ['store_ids' => $storeIds, 'store_mode' => $storeMode]];
        }

        // ── Mode: none ────────────────────────────────────────────────────────
        if ($storeMode === 'none') {
            // Only global Spatie permissions count here.
            // Store-scoped permissions are intentionally ignored.
            return $this->evaluatePermsAgainst(
                $rule,
                $userPermsGlobal,
                $tokenAbilities,
                [],
                'none'
            );
        }

        // ── Mode: scoped ──────────────────────────────────────────────────────
        if ($storeMode === 'scoped') {
            $storeIds = $this->extractStoreIds($rule, $storeContext);

            if (empty($storeIds)) {
                // Rule allows fallback to global perms when no store is provided?
                if (!empty($rule['store_allows_empty'])) {
                    return $this->evaluatePermsAgainst(
                        $rule,
                        $userPermsGlobal,
                        $tokenAbilities,
                        [],
                        'scoped-empty-allowed'
                    );
                }

                return [
                    false,
                    $this->requiredPermsFromRule($rule),
                    'deny-no-store',
                    ['store_ids' => [], 'store_mode' => 'scoped'],
                ];
            }

            $policy = (string) ($rule['store_match_policy'] ?? 'all');

            // For each store, evaluate using ONLY that store's effective permissions.
            // Global permissions are intentionally NOT used here.
            $perStoreAuth = [];
            foreach ($storeIds as $sid) {
                $storePerms = $this->getEffectivePermissionsForUserStoreCached($userId, (int) $sid);
                $perStoreAuth[$sid] = $this->evaluatePermsBoolean($rule, $storePerms, $tokenAbilities);
            }

            if ($policy === 'any') {
                if (in_array(true, $perStoreAuth, true)) {
                    return [
                        true,
                        [],
                        'store-permissions-any',
                        ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth],
                    ];
                }

                return [
                    false,
                    $this->requiredPermsFromRule($rule),
                    'deny-store-any',
                    ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth],
                ];
            }

            // Default policy: "all" — user must be authorized for every store
            foreach ($perStoreAuth as $ok) {
                if (!$ok) {
                    return [
                        false,
                        $this->requiredPermsFromRule($rule),
                        'deny-store-all',
                        ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth],
                    ];
                }
            }

            return [
                true,
                [],
                'store-permissions-all',
                ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth],
            ];
        }

        // ── Mode: all_stores ──────────────────────────────────────────────────
        if ($storeMode === 'all_stores') {
            // Fast path: rule defines special all-store access roles
            $allRolesAny = (array) ($rule['store_all_access_roles_any'] ?? []);
            if (!empty($allRolesAny) && $this->hasAny($userRolesGlobal, $allRolesAny)) {
                return [true, [], 'all-stores-roles', ['store_ids' => [], 'store_mode' => 'all_stores']];
            }

            // Fast path: rule defines special all-store access permissions
            $allPermsAny = (array) ($rule['store_all_access_permissions_any'] ?? []);
            if (!empty($allPermsAny)) {
                $userHas = $this->hasAny($userPermsGlobal, $allPermsAny);
                $tokenOk = $this->abilitiesCoverAny($tokenAbilities, $allPermsAny);
                if ($userHas && $tokenOk) {
                    return [true, $allPermsAny, 'all-stores-permissions-any', ['store_ids' => [], 'store_mode' => 'all_stores']];
                }
            }

            // Slow path: verify the user actually has assignments for every active store
            if (!$this->userHasAllActiveStoresCached($userId)) {
                return [
                    false,
                    $this->requiredPermsFromRule($rule),
                    'deny-all-stores',
                    ['store_ids' => [], 'store_mode' => 'all_stores'],
                ];
            }

            // If user covers all stores, evaluate using global permissions
            return $this->evaluatePermsAgainst(
                $rule,
                $userPermsGlobal,
                $tokenAbilities,
                [],
                'all_stores'
            );
        }

        // ── Unknown mode → deny ───────────────────────────────────────────────
        return [
            false,
            $this->requiredPermsFromRule($rule),
            'deny-invalid-store-mode',
            ['store_ids' => [], 'store_mode' => $storeMode],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERMISSION HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate permissions_any / permissions_all against a given permission set.
     * Returns a full [authorized, required, grantedBy, meta] tuple.
     */
    private function evaluatePermsAgainst(
        array $rule,
        array $userPerms,
        array $tokenAbilities,
        array $storeIds,
        string $mode
    ): array {
        $permsAny = (array) ($rule['permissions_any'] ?? []);
        $permsAll = (array) ($rule['permissions_all'] ?? []);

        if (!empty($permsAny)) {
            $userHas = $this->hasAny($userPerms, $permsAny);
            $tokenOk = $this->abilitiesCoverAny($tokenAbilities, $permsAny);
            if ($userHas && $tokenOk) {
                return [true, $permsAny, 'permissions_any', ['store_ids' => $storeIds, 'store_mode' => $mode]];
            }
        }

        if (!empty($permsAll)) {
            $userHas = $this->hasAll($userPerms, $permsAll);
            $tokenOk = $this->abilitiesCoverAll($tokenAbilities, $permsAll);
            if ($userHas && $tokenOk) {
                return [true, $permsAll, 'permissions_all', ['store_ids' => $storeIds, 'store_mode' => $mode]];
            }
        }

        // If rule has no permission requirements at all → allow by default
        if (empty($permsAny) && empty($permsAll)) {
            return [true, [], 'no-perms-required', ['store_ids' => $storeIds, 'store_mode' => $mode]];
        }

        return [false, $this->requiredPermsFromRule($rule), 'deny', ['store_ids' => $storeIds, 'store_mode' => $mode]];
    }

    /**
     * Boolean-only permission check (used per-store in policy loops).
     * Does NOT return a full tuple — just true/false.
     */
    private function evaluatePermsBoolean(array $rule, array $userPerms, array $tokenAbilities): bool
    {
        $permsAny = (array) ($rule['permissions_any'] ?? []);
        $permsAll = (array) ($rule['permissions_all'] ?? []);

        if (!empty($permsAny)) {
            if ($this->hasAny($userPerms, $permsAny) && $this->abilitiesCoverAny($tokenAbilities, $permsAny)) {
                return true;
            }
        }

        if (!empty($permsAll)) {
            if ($this->hasAll($userPerms, $permsAll) && $this->abilitiesCoverAll($tokenAbilities, $permsAll)) {
                return true;
            }
        }

        // No permission requirements on rule → allow
        if (empty($permsAny) && empty($permsAll)) {
            return true;
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE ID EXTRACTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract integer store IDs from the structured storeContext using the
     * rule's store_id_sources definition (or sensible defaults).
     *
     * storeContext shape:
     *   [ 'path' => [...], 'query' => [...], 'body' => [...] ]
     *
     * store_id_sources shape (stored as JSON in DB):
     *   { "path": ["store_id"], "query": ["store_id","store_ids"], "body": ["store_id","filters.store_ids"] }
     */
    private function extractStoreIds(array $rule, array $storeContext): array
    {
        $mode = (string) ($rule['store_scope_mode'] ?? 'none');
        if ($mode === 'none') {
            return [];
        }

        $sources = $rule['store_id_sources'] ?? null;

        if (!is_array($sources)) {
            $sources = [
                'path' => ['store_id', 'storeId', 'store'],
                'query' => ['store_id', 'store_ids', 'storeId', 'storeIds', 'stores', 'store'],
                'body' => ['store_id', 'store_ids', 'storeIds', 'stores', 'store', 'filters.store_ids', 'filters.store_id'],
                'header' => ['X-Store-Id', 'X-Store-Ids', 'X-StoreId', 'X-StoreIds', 'store_id', 'store_ids', 'storeId', 'storeIds', 'store'],
            ];
        }

        $collected = [];

        foreach (['path', 'query', 'body', 'header'] as $bucket) {
            $bucketData = $storeContext[$bucket] ?? [];
            $paths = (array) ($sources[$bucket] ?? []);

            foreach ($paths as $dotPath) {
                $val = $this->getByDotPath($bucketData, (string) $dotPath);
                foreach ($this->normalizeStoreIds($val) as $sid) {
                    $collected[] = $sid;
                }
            }
        }

        // Deduplicate and ensure positive integers only
        return array_values(
            array_unique(
                array_filter($collected, fn($v) => is_int($v) && $v > 0)
            )
        );
    }

    /**
     * Navigate a dot-path (e.g. "filters.store_ids") inside a nested array.
     */
    private function getByDotPath($arr, string $path)
    {
        if (!is_array($arr) || $path === '') {
            return null;
        }

        $cur = $arr;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return null;
            }
            $cur = $cur[$part];
        }

        return $cur;
    }

    /**
     * Normalize a value (scalar or array) into an array of positive integers.
     */
    private function normalizeStoreIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        // Flatten input into a simple list of scalar candidates
        $items = is_array($value) ? $value : [$value];

        $flattened = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                foreach ($item as $nested) {
                    $flattened[] = $nested;
                }
                continue;
            }

            // Support comma-separated headers like: "10,11,12"
            if (is_string($item) && str_contains($item, ',')) {
                foreach (explode(',', $item) as $part) {
                    $flattened[] = trim($part);
                }
                continue;
            }

            $flattened[] = $item;
        }

        $result = [];

        foreach ($flattened as $item) {
            if ($item === null || $item === '') {
                continue;
            }

            if (is_numeric($item) && (int) $item > 0) {
                // Integer PK
                $result[] = (int) $item;
                continue;
            }

            if (is_string($item)) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                // Numeric string after trim
                if (is_numeric($item) && (int) $item > 0) {
                    $result[] = (int) $item;
                    continue;
                }

                // Store code, e.g. "03795-00001"
                $pk = $this->resolveStoreStringIdCached($item);
                if ($pk !== null) {
                    $result[] = $pk;
                }
            }
        }

        return array_values(array_unique($result));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CACHED DATA FETCHERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load active rules for a service+method, ordered by priority, from Redis.
     */

    /**
     * Resolve a string store_id (e.g. "03795-00001") to the integer PK.
     * Cached per store code to avoid repeated DB hits.
     */
    private function resolveStoreStringIdCached(string $storeCode): ?int
    {
        $key = 'authz:store_code:' . hash('sha256', $storeCode);

        $pk = Cache::store('redis')->remember($key, 300, function () use ($storeCode) {
            return Store::where('store_id', $storeCode)
                ->where('is_active', true)
                ->value('id');
        });

        return $pk ? (int) $pk : null;
    }

    private function getRulesCached(string $service, string $method, int $version): array
    {
        $key = "authz:rules:v{$version}:{$service}:{$method}";

        return Cache::store('redis')->remember($key, 60, function () use ($service, $method) {
            return AuthRule::where('service', $service)
                ->where('method', strtoupper($method))
                ->where('is_active', true)
                ->orderBy('priority', 'asc')   // ← was missing; lower number = higher priority
                ->get()
                ->toArray();
        });
    }

    /**
     * Effective permissions the user holds specifically for one store.
     * Uses only store-scoped role hierarchy — NOT global Spatie permissions.
     */
    private function getEffectivePermissionsForUserStoreCached(int $userId, int $storeId): array
    {
        $key = "authz:eff_perms:u{$userId}:st{$storeId}";

        return Cache::store('redis')->remember($key, 60, function () use ($userId, $storeId) {
            $user = User::findOrFail($userId);

            // getEffectivePermissionsForStore() walks the store role hierarchy
            // and returns only permissions tied to that store's role assignments.
            return $user->getEffectivePermissionsForStore($storeId)
                ->pluck('name')
                ->values()
                ->all();
        });
    }

    /**
     * Check whether the user has active assignments for every active store.
     */
    private function userHasAllActiveStoresCached(int $userId): bool
    {
        $key = "authz:allstores:u{$userId}";

        return (bool) Cache::store('redis')->remember($key, 60, function () use ($userId) {
            $activeStoreIds = Store::where('is_active', true)
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->all();

            if (count($activeStoreIds) === 0) {
                return true;
            }

            $userStoreIds = DB::table('user_role_store')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->distinct()
                ->pluck('store_id')
                ->map(fn($v) => (int) $v)
                ->all();

            // Every active store must appear in the user's assignments
            $remaining = array_diff($activeStoreIds, $userStoreIds);

            return count($remaining) === 0;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ARRAY HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function hasAny(array $haystack, array $needles): bool
    {
        return !empty($needles) && !empty(array_intersect($haystack, $needles));
    }

    private function hasAll(array $haystack, array $needles): bool
    {
        return !empty($needles) && count(array_diff($needles, $haystack)) === 0;
    }

    private function requiredPermsFromRule(array $rule): array
    {
        return array_values(array_unique(array_merge(
            (array) ($rule['permissions_any'] ?? []),
            (array) ($rule['permissions_all'] ?? [])
        )));
    }

    private function abilitiesCoverAny(array $abilities, array $perms): bool
    {
        if (empty($abilities) || in_array('*', $abilities, true)) {
            return true;
        }
        $map = array_flip($abilities);
        foreach ($perms as $p) {
            if (isset($map[$p])) {
                return true;
            }
        }
        return false;
    }

    private function abilitiesCoverAll(array $abilities, array $perms): bool
    {
        if (empty($abilities) || in_array('*', $abilities, true)) {
            return true;
        }
        $map = array_flip($abilities);
        foreach ($perms as $p) {
            if (!isset($map[$p])) {
                return false;
            }
        }
        return true;
    }
}

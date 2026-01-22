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
     * @return array [bool authorized, array required_permissions, string granted_by, array meta]
     */
    public function check(
        string $service,
        string $method,
        string $path,
        ?string $routeName,
        array $userRolesGlobal,
        array $userPermsGlobal,
        array $tokenAbilities,
        array $storeContext,
        int $userId
    ): array {
        $method = strtoupper($method);
        $cfg = config('authz', []);
        $cache = Cache::store('redis');

        // --- versioning for rule changes (you can bump this key whenever rules change) ---
        $ver = (int) $cache->get('authz:ver', 1);

        // 1) Super role bypass
        $superRoles = (array) ($cfg['super_roles'] ?? []);
        if ($this->hasAny($userRolesGlobal, $superRoles)) {
            return [true, [], 'super-role', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // 2) Resolve matched rule (cached rules list)
        $rules = $this->getRulesCached($cache, $ver, $service, $method);

        $matched = null;

        // 3) RouteName precedence if provided
        if ($routeName) {
            foreach ($rules as $r) {
                if (!empty($r['route_name']) && $r['route_name'] === $routeName) {
                    $matched = $r;
                    break;
                }
            }
        }

        // 4) Path match
        if (!$matched) {
            foreach ($rules as $r) {
                if (empty($r['path_regex'])) continue;
                if (@preg_match($r['path_regex'], $path) === 1) {
                    $matched = $r;
                    break;
                }
            }
        }

        if (!$matched) {
            $allow = (bool) ($cfg['allow_if_no_rule'] ?? false);
            return [$allow, [], 'no-rule', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // --- Decision cache key (token-specific caching happens outside here; this is authz-only) ---
        $ctxKey = $routeName ?: ($method . ' ' . $path);
        $storeIdsForKey = $this->extractStoreIds($matched, $storeContext);
        sort($storeIdsForKey);
        $storeKey = implode(',', $storeIdsForKey);

        $decisionKey = 'authz:decision:v' . $ver
            . ':' . hash('sha256', $service . '|' . $ctxKey)
            . ':u' . $userId
            . ':s' . hash('sha256', $storeKey);

        $cachedDecision = $cache->get($decisionKey);
        if (is_array($cachedDecision) && isset($cachedDecision['authorized'])) {
            return [
                (bool)$cachedDecision['authorized'],
                (array)($cachedDecision['required_permissions'] ?? []),
                (string)($cachedDecision['granted_by'] ?? 'cache'),
                (array)($cachedDecision['meta'] ?? ['store_ids' => $storeIdsForKey, 'store_mode' => ($matched['store_scope_mode'] ?? 'none')]),
            ];
        }

        // 5) Evaluate rule (store-aware)
        $result = $this->evaluateRule(
            $matched,
            $userRolesGlobal,
            $userPermsGlobal,
            $tokenAbilities,
            $storeContext,
            $userId
        );

        // Cache decision briefly (bounded & safe)
        $cache->put($decisionKey, [
            'authorized'           => $result[0],
            'required_permissions' => $result[1],
            'granted_by'           => $result[2],
            'meta'                 => $result[3],
        ], now()->addSeconds((int)($cfg['decision_cache_seconds'] ?? 20)));

        return $result;
    }

    /**
     * Cache active rules for service+method with versioning.
     */
    private function getRulesCached($cache, int $ver, string $service, string $method): array
    {
        $rulesKey = 'authz:rules:v' . $ver . ':' . hash('sha256', $service . '|' . $method);

        return $cache->remember($rulesKey, now()->addSeconds(60), function () use ($service, $method) {
            $rules = AuthRule::query()
                ->where('service', $service)
                ->where('is_active', true)
                ->whereIn('method', [$method, 'ANY'])
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

            // Convert to array for fast iteration & to keep Redis payload small
            return $rules->map(function (AuthRule $r) {
                return [
                    'id'                           => $r->id,
                    'service'                      => $r->service,
                    'method'                       => $r->method,
                    'path_regex'                   => $r->path_regex,
                    'route_name'                   => $r->route_name,
                    'roles_any'                    => $r->roles_any ?: [],
                    'permissions_any'              => $r->permissions_any ?: [],
                    'permissions_all'              => $r->permissions_all ?: [],
                    'store_scope_mode'             => $r->store_scope_mode ?: 'none',
                    'store_id_sources'             => $r->store_id_sources ?: null,
                    'store_match_policy'           => $r->store_match_policy ?: 'all',
                    'store_allows_empty'           => (bool)$r->store_allows_empty,
                    'store_all_access_roles_any'   => $r->store_all_access_roles_any ?: [],
                    'store_all_access_permissions_any' => $r->store_all_access_permissions_any ?: [],
                    'priority'                     => (int)$r->priority,
                ];
            })->values()->all();
        });
    }

    private function evaluateRule(
        array $rule,
        array $userRolesGlobal,
        array $userPermsGlobal,
        array $tokenAbilities,
        array $storeContext,
        int $userId
    ): array {
        $storeMode = (string)($rule['store_scope_mode'] ?? 'none');
        $storeIds = $this->extractStoreIds($rule, $storeContext);

        // Global roles-any bypass (applies regardless of store mode)
        $rolesAny = (array)($rule['roles_any'] ?? []);
        if (!empty($rolesAny) && $this->hasAny($userRolesGlobal, $rolesAny)) {
            return [true, [], 'roles', ['store_ids' => $storeIds, 'store_mode' => $storeMode]];
        }

        // Store mode: none => use global perms
        if ($storeMode === 'none') {
            return $this->evaluatePermsAgainst(
                $rule,
                $userPermsGlobal,
                $tokenAbilities,
                $storeIds,
                'none'
            );
        }

        // Store mode: scoped
        if ($storeMode === 'scoped') {
            if (empty($storeIds)) {
                if (!empty($rule['store_allows_empty'])) {
                    // if allowed empty, fall back to global perms
                    return $this->evaluatePermsAgainst(
                        $rule,
                        $userPermsGlobal,
                        $tokenAbilities,
                        $storeIds,
                        'scoped-empty-allowed'
                    );
                }

                $required = $this->requiredPermsFromRule($rule);
                return [false, $required, 'deny-no-store', ['store_ids' => [], 'store_mode' => 'scoped']];
            }

            $policy = (string)($rule['store_match_policy'] ?? 'all');

            // For each storeId, compute effective permissions and check rule
            $perStoreAuth = [];
            foreach ($storeIds as $sid) {
                $storePerms = $this->getEffectivePermissionsForUserStoreCached($userId, $sid);
                $ok = $this->evaluatePermsBoolean($rule, $storePerms, $tokenAbilities);
                $perStoreAuth[$sid] = $ok;
            }

            if ($policy === 'any') {
                if (in_array(true, $perStoreAuth, true)) {
                    return [true, [], 'store-permissions-any', ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth]];
                }
                $required = $this->requiredPermsFromRule($rule);
                return [false, $required, 'deny-store-any', ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth]];
            }

            // default: all
            foreach ($perStoreAuth as $ok) {
                if (!$ok) {
                    $required = $this->requiredPermsFromRule($rule);
                    return [false, $required, 'deny-store-all', ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth]];
                }
            }

            return [true, [], 'store-permissions-all', ['store_ids' => $storeIds, 'store_mode' => 'scoped', 'per_store' => $perStoreAuth]];
        }

        // Store mode: all_stores
        if ($storeMode === 'all_stores') {
            // 1) if user has explicit "all stores" role/permission in rule, allow quickly
            $allRolesAny = (array)($rule['store_all_access_roles_any'] ?? []);
            if (!empty($allRolesAny) && $this->hasAny($userRolesGlobal, $allRolesAny)) {
                return [true, [], 'all-stores-roles', ['store_ids' => [], 'store_mode' => 'all_stores']];
            }

            $allPermsAny = (array)($rule['store_all_access_permissions_any'] ?? []);
            if (!empty($allPermsAny)) {
                $userHas = $this->hasAny($userPermsGlobal, $allPermsAny);
                $tokenOk = $this->abilitiesCoverAny($tokenAbilities, $allPermsAny);
                if ($userHas && $tokenOk) {
                    return [true, $allPermsAny, 'all-stores-permissions-any', ['store_ids' => [], 'store_mode' => 'all_stores']];
                }
            }

            // 2) otherwise: required by you -> ACTUALLY verify user has assignments for ALL active stores
            $hasAllStores = $this->userHasAllActiveStoresCached($userId);
            if (!$hasAllStores) {
                $required = $this->requiredPermsFromRule($rule);
                return [false, $required, 'deny-all-stores', ['store_ids' => [], 'store_mode' => 'all_stores']];
            }

            // 3) if user has all stores, evaluate perms globally (same as your current approach)
            return $this->evaluatePermsAgainst(
                $rule,
                $userPermsGlobal,
                $tokenAbilities,
                [],
                'all-stores'
            );
        }

        // Unknown mode => deny
        $required = $this->requiredPermsFromRule($rule);
        return [false, $required, 'deny-invalid-store-mode', ['store_ids' => $storeIds, 'store_mode' => $storeMode]];
    }

    private function evaluatePermsAgainst(array $rule, array $userPerms, array $tokenAbilities, array $storeIds, string $mode): array
    {
        $permsAny = (array)($rule['permissions_any'] ?? []);
        $permsAll = (array)($rule['permissions_all'] ?? []);

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

        $required = $this->requiredPermsFromRule($rule);
        return [false, $required, 'deny', ['store_ids' => $storeIds, 'store_mode' => $mode]];
    }

    private function evaluatePermsBoolean(array $rule, array $userPerms, array $tokenAbilities): bool
    {
        $permsAny = (array)($rule['permissions_any'] ?? []);
        $permsAll = (array)($rule['permissions_all'] ?? []);

        if (!empty($permsAny)) {
            $userHas = $this->hasAny($userPerms, $permsAny);
            $tokenOk = $this->abilitiesCoverAny($tokenAbilities, $permsAny);
            if ($userHas && $tokenOk) return true;
        }

        if (!empty($permsAll)) {
            $userHas = $this->hasAll($userPerms, $permsAll);
            $tokenOk = $this->abilitiesCoverAll($tokenAbilities, $permsAll);
            if ($userHas && $tokenOk) return true;
        }

        return false;
    }

    private function requiredPermsFromRule(array $rule): array
    {
        $permsAny = (array)($rule['permissions_any'] ?? []);
        $permsAll = (array)($rule['permissions_all'] ?? []);
        return !empty($permsAny) ? $permsAny : $permsAll;
    }

    /**
     * Extract store IDs from store_context using rule.store_id_sources
     */
    private function extractStoreIds(array $rule, array $storeContext): array
    {
        $mode = (string)($rule['store_scope_mode'] ?? 'none');
        if ($mode === 'none') return [];

        $sources = $rule['store_id_sources'] ?? null;

        // Default behavior (if not set): look for common keys everywhere
        if (!is_array($sources)) {
            $sources = [
                'path'  => ['store_id', 'storeId'],
                'query' => ['store_id', 'store_ids', 'storeIds', 'stores', 'store'],
                'body'  => ['store_id', 'store_ids', 'storeIds', 'stores', 'store', 'filters.store_ids', 'filters.store_id'],
            ];
        }

        $collected = [];

        foreach (['path', 'query', 'body'] as $bucket) {
            $bucketData = $storeContext[$bucket] ?? [];
            $paths = (array)($sources[$bucket] ?? []);
            foreach ($paths as $dotPath) {
                $val = $this->getByDotPath($bucketData, (string)$dotPath);
                foreach ($this->normalizeStoreIds($val) as $sid) {
                    $collected[] = $sid;
                }
            }
        }

        $collected = array_values(array_unique(array_filter($collected, fn($v) => is_int($v) && $v > 0)));
        return $collected;
    }

    private function normalizeStoreIds($value): array
    {
        if ($value === null) return [];

        // scalar
        if (is_int($value)) return [$value];
        if (is_string($value) && ctype_digit($value)) return [(int)$value];

        // array
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                foreach ($this->normalizeStoreIds($v) as $sid) {
                    $out[] = $sid;
                }
            }
            return $out;
        }

        return [];
    }

    private function getByDotPath($arr, string $path)
    {
        if (!is_array($arr)) return null;
        if ($path === '') return null;

        $parts = explode('.', $path);
        $cur = $arr;

        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return null;
            }
            $cur = $cur[$p];
        }

        return $cur;
    }

    /**
     * Cached effective permissions for user+store.
     */
    private function getEffectivePermissionsForUserStoreCached(int $userId, int $storeId): array
    {
        $cache = Cache::store('redis');
        $key = 'authz:eff_perms:u' . $userId . ':st' . $storeId;

        return $cache->remember($key, now()->addSeconds(60), function () use ($userId, $storeId) {
            /** @var User $user */
            $user = User::findOrFail($userId);
            // this will query Spatie roles/permissions, and your hierarchy tables as needed
            return $user->getEffectivePermissionsForStore($storeId)->pluck('name')->values()->all();
        });
    }

    /**
     * Must test "user has ALL existing active stores" but cache result.
     */
    private function userHasAllActiveStoresCached(int $userId): bool
    {
        $cache = Cache::store('redis');
        $key = 'authz:allstores:u' . $userId;

        return (bool)$cache->remember($key, now()->addSeconds(60), function () use ($userId) {
            $activeStoreIds = Store::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn($v) => (int)$v)
                ->values()
                ->all();

            $totalActive = count($activeStoreIds);
            if ($totalActive === 0) return true;

            $userStores = DB::table('user_role_store')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->distinct()
                ->pluck('store_id')
                ->map(fn($v) => (int)$v)
                ->values()
                ->all();

            // Must include every active store
            $activeMap = array_flip($activeStoreIds);
            foreach ($userStores as $sid) {
                unset($activeMap[$sid]);
            }

            return count($activeMap) === 0;
        });
    }

    private function hasAny(array $haystack, array $needles): bool
    {
        if (empty($needles)) return false;
        $map = array_flip($haystack);
        foreach ($needles as $n) if (isset($map[$n])) return true;
        return false;
    }

    private function hasAll(array $haystack, array $needles): bool
    {
        if (empty($needles)) return false;
        $map = array_flip($haystack);
        foreach ($needles as $n) if (!isset($map[$n])) return false;
        return true;
    }

    private function abilitiesCoverAny(array $abilities, array $perms): bool
    {
        if (empty($abilities)) return true;
        if (in_array('*', $abilities, true)) return true;
        $map = array_flip($abilities);
        foreach ($perms as $p) if (isset($map[$p])) return true;
        return false;
    }

    private function abilitiesCoverAll(array $abilities, array $perms): bool
    {
        if (empty($abilities)) return true;
        if (in_array('*', $abilities, true)) return true;
        $map = array_flip($abilities);
        foreach ($perms as $p) if (!isset($map[$p])) return false;
        return true;
    }
}

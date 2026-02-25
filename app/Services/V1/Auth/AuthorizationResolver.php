<?php

namespace App\Services\V1\Auth;

use App\Models\AuthRule;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuthorizationResolver
{

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
        // Check super admin roles that bypass all checks
        $superRoles = (array) config('authz.super_roles', []);
        $user = User::find($userId);
        $userRolesGlobal = $user->roles->pluck('name')->toArray();

        // If user has any super roles, allow them without further checks
        if ($this->hasAny($userRolesGlobal, $superRoles)) {
            return [true, [], 'super-role', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // --- versioning for rule changes (bump version if rules change) ---
        $ver = (int) Cache::get('authz:ver', 1);

        // Get cached rules for this service and method
        $rules = $this->getRulesCached($service, $method, $ver);

        $matched = null;
        $storeScopeMode = 'none'; // Default

        // 1) Check for routeName match first
        if ($routeName) {
            foreach ($rules as $rule) {
                if ($rule['route_name'] === $routeName) {
                    $matched = $rule;
                    $storeScopeMode = $rule['store_scope_mode'] ?? 'none';
                    break;
                }
            }
        }

        // 2) If routeName doesn't match, fallback to path match
        if (!$matched) {
            foreach ($rules as $rule) {
                if (!empty($rule['path_regex']) && preg_match($rule['path_regex'], $path)) {
                    $matched = $rule;
                    $storeScopeMode = $rule['store_scope_mode'] ?? 'none';
                    break;
                }
            }
        }

        // If no rule matched, apply fallback logic
        if (!$matched) {
            return [false, [], 'no-rule', ['store_ids' => [], 'store_mode' => 'none']];
        }

        // 3) Evaluate based on store scope mode
        if ($storeScopeMode === 'none') {
            // No store scope - use global roles and permissions
            return $this->evaluateGlobalRolesAndPermissions($userId, $matched, $userRolesGlobal, $userPermsGlobal, $tokenAbilities);
        }

        // 4) If store-scoped, check user roles and permissions for the specific store
        $storeIds = $this->extractStoreIdsFromContext($storeContext);
        if (empty($storeIds)) {
            return [false, $this->requiredPermsFromRule($matched), 'deny-no-store', ['store_ids' => [], 'store_mode' => 'scoped']];
        }

        // Evaluate permissions based on the specific store
        $storePermissionsCheck = $this->evaluateStorePermissions($userId, $storeIds, $matched, $userRolesGlobal, $userPermsGlobal, $tokenAbilities);

        if ($storePermissionsCheck[0]) {
            return $storePermissionsCheck; // Authorized for specific store
        }

        return [false, $this->requiredPermsFromRule($matched), 'deny-store', ['store_ids' => $storeIds, 'store_mode' => 'scoped']];
    }

    private function evaluateGlobalRolesAndPermissions(int $userId, array $matchedRule, array $userRolesGlobal, array $userPermsGlobal, array $tokenAbilities)
    {
        // Check global roles and permissions
        $permsAny = (array) $matchedRule['permissions_any'];
        $permsAll = (array) $matchedRule['permissions_all'];

        if ($this->hasAny($userRolesGlobal, $permsAny) || $this->hasAll($userPermsGlobal, $permsAll)) {
            return [true, [], 'global-role-permission', ['store_ids' => [], 'store_mode' => 'none']];
        }

        return [false, $this->requiredPermsFromRule($matchedRule), 'deny-global-permission', ['store_ids' => [], 'store_mode' => 'none']];
    }

    private function evaluateStorePermissions(
        int $userId,
        array $storeIds,
        array $matchedRule,
        array $userRolesGlobal,
        array $userPermsGlobal,
        array $tokenAbilities
    ) {
        $storeRoles = [];
        foreach ($storeIds as $storeId) {
            $effectiveRoles = (new User)->getEffectiveRolesForStore($storeId);
            $storeRoles = $storeRoles->merge($effectiveRoles);
        }

        // Check store-specific roles and permissions
        $storePermsAny = (array) $matchedRule['permissions_any'];
        $storePermsAll = (array) $matchedRule['permissions_all'];

        if ($this->hasAny($storeRoles, $storePermsAny) || $this->hasAll($userPermsGlobal, $storePermsAll)) {
            return [true, [], 'store-role-permission', ['store_ids' => $storeIds, 'store_mode' => 'scoped']];
        }

        return [false, $this->requiredPermsFromRule($matchedRule), 'deny-store-permission', ['store_ids' => $storeIds, 'store_mode' => 'scoped']];
    }


    private function hasAny(array $haystack, array $needles): bool
    {
        return !empty(array_intersect($haystack, $needles));
    }

    private function hasAll(array $haystack, array $needles): bool
    {
        return !empty($needles) && !array_diff($needles, $haystack);
    }

    private function requiredPermsFromRule(array $rule): array
    {
        return array_merge((array) $rule['permissions_any'], (array) $rule['permissions_all']);
    }

    // This method remains the same; its purpose is to fetch the cached rules from Redis
    private function getRulesCached(string $service, string $method, int $version): array
    {
        $cache = Cache::store('redis');
        $cacheKey = "authz:rules:v{$version}:{$service}:{$method}";
        return $cache->remember($cacheKey, 60, function () use ($service, $method) {
            return AuthRule::where('service', $service)
                ->where('method', strtoupper($method))
                ->where('is_active', true)
                ->get()
                ->toArray();
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

    private function normalizeStoreIds(array $storeIds): array
    {
        // Normalize store IDs by ensuring that we handle them as strings and remove any invalid values
        return array_filter(array_map('strval', $storeIds), function ($id) {
            return !empty($id);  // Remove any empty or null values
        });
    }

    private function extractStoreIdsFromContext(array $storeContext): array
    {
        // Extract store IDs from the store context to identify which stores the user is acting on
        $storeIds = [];
        foreach (['path', 'query', 'body'] as $key) {
            if (isset($storeContext[$key])) {
                // Ensure store IDs are always strings
                $storeIds = array_merge($storeIds, $this->normalizeStoreIds($storeContext[$key]));
            }
        }
        return array_values(array_unique($storeIds));  // Return unique store IDs as an array
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

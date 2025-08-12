<?php

namespace App\Services\V1\Auth;

use App\Models\AuthRule;

class AuthorizationResolver
{
    /**
     * @return array [bool authorized, array required_permissions, string granted_by]
     */
    public function check(
        string $service,
        string $method,
        string $path,
        ?string $routeName,
        array $userRoles,
        array $userPerms,
        array $tokenAbilities
    ): array {
        $method = strtoupper($method);
        $cfg = config('authz');

        // 1) Super role bypass
        $superRoles = (array) ($cfg['super_roles'] ?? []);
        if ($this->hasAny($userRoles, $superRoles)) {
            return [true, [], 'super-role'];
        }

        // 2) Load active rules for this service + method (or ANY)
        $rules = AuthRule::query()
            ->where('service', $service)
            ->where('is_active', true)
            ->whereIn('method', [$method, 'ANY'])
            ->orderByDesc('priority')
            ->orderBy('id') // stable tie-break
            ->get();

        $matched = null;

        // 3) RouteName has precedence if provided
        if ($routeName) {
            foreach ($rules as $r) {
                if ($r->route_name && $r->route_name === $routeName) {
                    $matched = $r; break;
                }
            }
        }

        // 4) Path match (DSL compiled to regex)
        if (!$matched) {
            foreach ($rules as $r) {
                if (!$r->path_regex) continue;
                if (@preg_match($r->path_regex, $path) === 1) {
                    $matched = $r; break;
                }
            }
        }

        if (!$matched) {
            $allow = (bool) ($cfg['allow_if_no_rule'] ?? false);
            return [$allow, [], 'no-rule'];
        }

        // 5) Evaluate the matched rule
        $rolesAny = $rRoles = (array) ($matched->roles_any ?? []);
        if (!empty($rRoles) && $this->hasAny($userRoles, $rRoles)) {
            return [true, [], 'roles'];
        }

        $permsAny = (array) ($matched->permissions_any ?? []);
        $permsAll = (array) ($matched->permissions_all ?? []);

        if (!empty($permsAny)) {
            $userHas = $this->hasAny($userPerms, $permsAny);
            $tokenOk = $this->abilitiesCoverAny($tokenAbilities, $permsAny);
            if ($userHas && $tokenOk) {
                return [true, $permsAny, 'permissions_any'];
            }
        }

        if (!empty($permsAll)) {
            $userHas = $this->hasAll($userPerms, $permsAll);
            $tokenOk = $this->abilitiesCoverAll($tokenAbilities, $permsAll);
            if ($userHas && $tokenOk) {
                return [true, $permsAll, 'permissions_all'];
            }
        }

        $required = !empty($permsAny) ? $permsAny : $permsAll;
        return [false, (array) $required, 'deny'];
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

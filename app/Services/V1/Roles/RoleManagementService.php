<?php

namespace App\Services\V1\Roles;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleManagementService
{
    public function getAllRoles($perPage = 15, $search = null)
    {
        $query = Role::with('permissions');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (isset($data['permissions'])) {
            $role->givePermissionTo($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function updateRole(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return $role->load('permissions');
    }

    public function deleteRole(Role $role): bool
    {
        return $role->delete();
    }

    public function assignPermissionsToRole(Role $role, array $permissions): Role
    {
        $role->givePermissionTo($permissions);
        return $role->load('permissions');
    }

    public function removePermissionsFromRole(Role $role, array $permissions): Role
    {
        $role->revokePermissionTo($permissions);
        return $role->load('permissions');
    }

    public function syncRolePermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        return $role->load('permissions');
    }
}

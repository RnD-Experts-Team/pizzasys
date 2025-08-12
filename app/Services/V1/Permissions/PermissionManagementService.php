<?php

namespace App\Services\V1\Permissions;

use Spatie\Permission\Models\Permission;

class PermissionManagementService
{
    public function getAllPermissions($perPage = 15, $search = null)
    {
        $query = Permission::with('roles');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);
    }

    public function updatePermission(Permission $permission, array $data): Permission
    {
        $permission->update(['name' => $data['name']]);
        return $permission;
    }

    public function deletePermission(Permission $permission): bool
    {
        return $permission->delete();
    }

    public function getPermissionsByGuard(string $guard = 'web')
    {
        return Permission::where('guard_name', $guard)->get();
    }
}

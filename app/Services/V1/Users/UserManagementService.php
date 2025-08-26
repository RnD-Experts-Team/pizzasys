<?php

namespace App\Services\V1\Users;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserManagementService
{
    public function getAllUsers($perPage = 15, $search = null, $role = null)
    {
        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('id', $role)->orWhere('name', $role);
            });
        }

        // Load all necessary relationships
        $users = $query->with([
            'roles.permissions', 
            'permissions',
            'roleTenancies.role',
            'roleTenancies.store'
        ])->paginate($perPage);

        // Transform users to include role permissions and stores with roles
        $users->getCollection()->transform(function ($user) {
            // Get permissions from roles (not direct permissions)
            $rolePermissions = collect();
            foreach ($user->roles as $role) {
                $rolePermissions = $rolePermissions->merge($role->permissions);
            }
            $user->role_permissions = $rolePermissions->unique('id')->values();

            // Get stores with their associated roles
            $user->stores_with_roles = $user->getStoresWithRoles();

            // Clean up relationships to avoid over-exposure
            unset($user->roleTenancies);

            return $user;
        });

        return $users;
    }
public function getUserWithCompleteData(User $user): User
    {
        $user = $user->getWithRolesPermissionsAndStores();
        
        // Add computed properties
        $user->role_permissions = $user->getRolePermissions();
        $user->stores_with_roles = $user->getStoresWithRoles();
        
        return $user;
    }
    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(), // Auto-verify for admin created users
        ]);

        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->givePermissionTo($data['permissions']);
        }

        return $user->getWithRolesAndPermissions();
    }

    public function updateUser(User $user, array $data): User
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        
        if (isset($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Update roles
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Update permissions
        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return $user->getWithRolesAndPermissions();
    }

    public function deleteUser(User $user): bool
    {
        // Revoke all tokens before deletion
        $user->tokens()->delete();
        
        return $user->delete();
    }

    public function assignRolesToUser(User $user, array $roles): User
    {
        $user->assignRole($roles);
        return $user->getWithRolesAndPermissions();
    }

    public function removeRolesFromUser(User $user, array $roles): User
    {
        $user->removeRole($roles);
        return $user->getWithRolesAndPermissions();
    }

    public function syncUserRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);
        return $user->getWithRolesAndPermissions();
    }

    public function givePermissionsToUser(User $user, array $permissions): User
    {
        $user->givePermissionTo($permissions);
        return $user->getWithRolesAndPermissions();
    }

    public function revokePermissionsFromUser(User $user, array $permissions): User
    {
        $user->revokePermissionTo($permissions);
        return $user->getWithRolesAndPermissions();
    }

    public function syncUserPermissions(User $user, array $permissions): User
    {
        $user->syncPermissions($permissions);
        return $user->getWithRolesAndPermissions();
    }
}

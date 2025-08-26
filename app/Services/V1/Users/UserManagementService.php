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

        // ✅ FIXED - Load relationships correctly
        $users = $query->with([
            'roles.permissions', 
            'permissions',
            'roleTenancies.role.permissions', // Now this works because role() exists in pivot
            'roleTenancies.store'
        ])->paginate($perPage);

        // Transform to clean structure
        $users->getCollection()->transform(function ($user) {
            // Clean structure: roles + permissions + stores
            $transformed = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                
                // Roles with their permissions
                'roles' => $user->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->map(function($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name
                            ];
                        })
                    ];
                }),
                
                // Direct permissions
                'permissions' => $user->permissions->map(function($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                }),
                
                // Stores with roles in each store
                'stores' => $this->transformUserStores($user)
            ];

            return (object) $transformed;
        });

        return $users;
    }

    private function transformUserStores($user)
    {
        $storeGroups = $user->roleTenancies->groupBy('store_id');
        $stores = [];

        foreach ($storeGroups as $storeId => $assignments) {
            $store = $assignments->first()->store;
            $storeRoles = [];

            foreach ($assignments as $assignment) {
                $storeRoles[] = [
                    'id' => $assignment->role->id,
                    'name' => $assignment->role->name,
                    'permissions' => $assignment->role->permissions->map(function($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name
                        ];
                    })
                ];
            }

            $stores[] = [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name
                ],
                'roles' => $storeRoles
            ];
        }

        return $stores;
    }

    public function getUserWithCompleteData(User $user): User
    {
        $user = $user->load([
            'roles.permissions',
            'permissions',
            'roleTenancies.role.permissions',
            'roleTenancies.store'
        ]);

        // Add the same clean structure
        $user->role_permissions = $user->getRolePermissions();
        $user->stores_with_roles = $this->transformUserStores($user);

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

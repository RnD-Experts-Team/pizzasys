<?php

namespace App\Services\V1\Users;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\UserRoleStore;

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

        // ✅ FIXED - Simple eager loading without nested .role
        $users = $query->with([
            'roles.permissions', 
            'permissions'
        ])->paginate($perPage);

        // Transform to your exact structure
        $users->getCollection()->transform(function ($user) {
            // Get store data separately to avoid the relationship error
            $storeData = $this->getUserStores($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                
                // 1. Roles with their permissions
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
                
                // 2. Direct permissions
                'permissions' => $user->permissions->map(function($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                }),
                
                // 3. Stores with roles in each store
                'stores' => $storeData
            ];
        });

        return $users;
    }

    private function getUserStores($userId)
    {
        // Get user store assignments with roles and stores
        $assignments = UserRoleStore::where('user_id', $userId)
            ->where('is_active', true)
            ->with(['role.permissions', 'store'])
            ->get();

        // Group by store
        $storeGroups = $assignments->groupBy('store_id');
        $stores = [];

        foreach ($storeGroups as $storeId => $storeAssignments) {
            $store = $storeAssignments->first()->store;
            $roles = [];

            foreach ($storeAssignments as $assignment) {
                $roles[] = [
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
                'roles' => $roles
            ];
        }

        return $stores;
    }

    public function getUserWithCompleteData(User $user): array
    {
        $user = $user->load(['roles.permissions', 'permissions']);
        
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            
            // 1. Roles with permissions
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
            
            // 2. Direct permissions
            'permissions' => $user->permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name
                ];
            }),
            
            // 3. Stores with roles
            'stores' => $this->getUserStores($user->id)
        ];
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

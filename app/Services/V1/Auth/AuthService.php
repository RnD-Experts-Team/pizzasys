<?php

namespace App\Services\V1\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Role;
use App\Models\UserRoleStore;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->sendOtp($user->email, 'verification');

        return [
            'user' => $user,
            'message' => 'User registered successfully. Please verify your email with the OTP sent.'
        ];
    }

    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $userData = $this->getUserCompleteData($user);

        return [
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    public function sendOtp(string $email, string $type): void
    {
        // Delete existing unused OTPs
        Otp::where('email', $email)
            ->where('type', $type)
            ->where('used', false)
            ->delete();

        $otpCode = Otp::generateOtp();
        
        Otp::create([
            'email' => $email,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        Mail::to($email)->send(new OtpMail($otpCode, $type));
    }

    public function verifyOtp(string $email, string $otp, string $type): bool
    {
        $otpRecord = Otp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('used', false)
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return false;
        }

        $otpRecord->markAsUsed();

        if ($type === 'verification') {
            User::where('email', $email)->update([
                'email_verified_at' => Carbon::now()
            ]);
        }

        return true;
    }

    public function resetPassword(string $email, string $password, string $otp): bool
    {
        if (!$this->verifyOtp($email, $otp, 'password_reset')) {
            return false;
        }

        User::where('email', $email)->update([
            'password' => Hash::make($password)
        ]);

        return true;
    }

    public function refreshToken(User $user): array
    {
        // Revoke current tokens
        $user->tokens()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

        public function getUserCompleteData(User $user): array
    {
        // Load basic user with global roles and permissions
        $user->load(['roles.permissions', 'permissions']);

        // Get all stores the user has access to
        $userStores = $user->stores()->wherePivot('is_active', true)->get();

        // Build store-specific data
        $storeData = [];
        foreach ($userStores as $store) {
            $storeRoles = $user->getRolesForStore($store->id);
            $effectiveRoles = $user->getEffectiveRolesForStore($store->id);
            $effectivePermissions = $user->getEffectivePermissionsForStore($store->id);

            // Get hierarchy information for this store
            $hierarchyInfo = $this->getStoreHierarchyForUser($user, $store->id, $storeRoles);

            $storeData[] = [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'metadata' => $store->metadata,
                    'is_active' => $store->is_active
                ],
                'direct_roles' => $storeRoles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'permissions' => $role->permissions->map(function($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'guard_name' => $permission->guard_name
                            ];
                        })
                    ];
                }),
                'effective_roles' => $effectiveRoles->map(function($role) use ($storeRoles) {
                    $isDirectRole = $storeRoles->contains('id', $role->id);
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'guard_name' => $role->guard_name,
                        'is_inherited' => !$isDirectRole,
                        'permissions' => $role->permissions->map(function($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'guard_name' => $permission->guard_name
                            ];
                        })
                    ];
                }),
                'effective_permissions' => $effectivePermissions->map(function($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name
                    ];
                }),
                'hierarchy_info' => $hierarchyInfo,
                'manageable_users' => $this->getManageableUsersInStore($user, $store->id),
                'assignment_metadata' => $this->getAssignmentMetadata($user, $store->id)
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            
            // Global roles and permissions (if any)
            'global_roles' => $user->roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(function($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'guard_name' => $permission->guard_name
                        ];
                    })
                ];
            }),
            
            'global_permissions' => $user->permissions->map(function($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name
                ];
            }),
            
            // All permissions (global + all stores)
            'all_permissions' => $this->getAllUserPermissions($user),
            
            // Store-specific data
            'stores' => $storeData,
            
            // Summary statistics
            'summary' => [
                'total_stores' => count($storeData),
                'total_roles' => $this->getTotalRolesCount($user),
                'total_permissions' => $this->getTotalPermissionsCount($user),
                'manageable_users_count' => $this->getTotalManageableUsersCount($user)
            ]
        ];
    }

    public function getStoreHierarchyForUser(User $user, string $storeId, $userRoles): array
    {
        $hierarchyInfo = [];
        
        foreach ($userRoles as $role) {
            // Get roles this role manages in this store
            $managedRoles = $role->lowerRolesInStore($storeId)->get();
            
            // Get roles that manage this role in this store
            $managingRoles = $role->higherRolesInStore($storeId)->get();
            
            $hierarchyInfo[] = [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name
                ],
                'manages_roles' => $managedRoles->map(function($managedRole) {
                    return [
                        'id' => $managedRole->id,
                        'name' => $managedRole->name
                    ];
                }),
                'managed_by_roles' => $managingRoles->map(function($managingRole) {
                    return [
                        'id' => $managingRole->id,
                        'name' => $managingRole->name
                    ];
                }),
                'can_manage_users' => $managedRoles->count() > 0,
                'hierarchy_level' => $this->calculateHierarchyLevel($role, $storeId)
            ];
        }
        
        return $hierarchyInfo;
    }

    public function getManageableUsersInStore(User $user, string $storeId): array
    {
        $manageableUsers = [];
        $userRoles = $user->getRolesForStore($storeId);
        
        foreach ($userRoles as $role) {
            $managedRoles = $role->lowerRolesInStore($storeId)->get();
            
            foreach ($managedRoles as $managedRole) {
                $usersWithManagedRole = User::whereHas('roleTenancies', function($q) use ($managedRole, $storeId) {
                    $q->where('role_id', $managedRole->id)
                      ->where('store_id', $storeId)
                      ->where('is_active', true);
                })->get();
                
                foreach ($usersWithManagedRole as $manageableUser) {
                    if ($manageableUser->id !== $user->id) { // Don't include self
                        $manageableUsers[] = [
                            'id' => $manageableUser->id,
                            'name' => $manageableUser->name,
                            'email' => $manageableUser->email,
                            'role' => [
                                'id' => $managedRole->id,
                                'name' => $managedRole->name
                            ],
                            'can_edit' => true,
                            'can_remove' => true,
                            'management_level' => $this->getManagementLevel($role, $managedRole, $storeId)
                        ];
                    }
                }
            }
        }
        
        // Remove duplicates and return unique users
        return collect($manageableUsers)->unique('id')->values()->all();
    }

    public function getAssignmentMetadata(User $user, string $storeId): array
    {
        return UserRoleStore::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->with('role')
            ->get()
            ->map(function($assignment) {
                return [
                    'role_id' => $assignment->role_id,
                    'role_name' => $assignment->role->name,
                    'metadata' => $assignment->metadata,
                    'assigned_at' => $assignment->created_at,
                    'is_active' => $assignment->is_active
                ];
            })->all();
    }

    public function getAllUserPermissions(User $user): array
    {
        $allPermissions = collect();
        
        // Global permissions
        $allPermissions = $allPermissions->merge($user->getAllPermissions());
        
        // Store-specific permissions
        $userStores = $user->stores()->wherePivot('is_active', true)->get();
        foreach ($userStores as $store) {
            $storePermissions = $user->getEffectivePermissionsForStore($store->id);
            $allPermissions = $allPermissions->merge($storePermissions);
        }
        
        return $allPermissions->unique('id')->map(function($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name
            ];
        })->values()->all();
    }

    public function getTotalRolesCount(User $user): int
    {
        $globalRoles = $user->roles->count();
        $storeRoles = UserRoleStore::where('user_id', $user->id)
            ->where('is_active', true)
            ->distinct('role_id')
            ->count();
        return $globalRoles + $storeRoles;
    }

    public function getTotalPermissionsCount(User $user): int
    {
        return count($this->getAllUserPermissions($user));
    }

    public function getTotalManageableUsersCount(User $user): int
    {
        $count = 0;
        $userStores = $user->stores()->wherePivot('is_active', true)->get();
        
        foreach ($userStores as $store) {
            $manageableUsers = $this->getManageableUsersInStore($user, $store->id);
            $count += count($manageableUsers);
        }
        
        return $count;
    }

    public function calculateHierarchyLevel(Role $role, string $storeId): int
    {
        $level = 0;
        $currentRole = $role;
        
        while (true) {
            $higherRoles = $currentRole->higherRolesInStore($storeId)->get();
            if ($higherRoles->isEmpty()) {
                break;
            }
            $level++;
            $currentRole = $higherRoles->first();
            
            // Prevent infinite loops
            if ($level > 10) break;
        }
        
        return $level;
    }

    public function getManagementLevel(Role $managerRole, Role $managedRole, string $storeId): string
    {
        if ($managerRole->lowerRolesInStore($storeId)->where('id', $managedRole->id)->exists()) {
            return 'direct';
        } elseif ($managerRole->getAllLowerRolesForStore($storeId)->contains('id', $managedRole->id)) {
            return 'indirect';
        }
        return 'none';
    }
}

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
    public function login(
        string $email,
        string $password,
        ?array $device = null,
        ?string $fcmToken = null,
        string $clientType = 'web'
    ): array {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        $tokenResult = $user->createToken('auth-token');
        $token = $tokenResult->plainTextToken;

        if ($clientType === 'mobile' || $device || $fcmToken) {
            $this->upsertUserDevice($user, $device, $fcmToken);
        }

        $userData = $this->getUserCompleteData($user);

        return [
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    protected function upsertUserDevice(User $user, ?array $device, ?string $fcmToken): void
    {
        $deviceId = data_get($device, 'device_id');

        $query = $user->devices();

        if ($deviceId) {
            $query->where('device_id', $deviceId);
        } else {
            $query->where('platform', data_get($device, 'platform'))
                ->where('model', data_get($device, 'model'));
        }

        $payload = [
            'device_id' => $deviceId,
            'platform' => data_get($device, 'platform'),
            'model' => data_get($device, 'model'),
            'os_version' => data_get($device, 'os_version'),
            'app_version' => data_get($device, 'app_version'),
            'last_seen_at' => now(),
        ];

        if ($fcmToken) {
            $payload['fcm_token'] = $fcmToken;
        }

        $payload = array_filter($payload, fn($v) => !is_null($v));

        $existing = $query->first();

        if ($existing) {
            $existing->update($payload);
        } else {
            $user->devices()->create($payload);
        }
    }

    public function sendOtp(string $email, string $type): void
    {
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

    public function checkOtp(string $email, string $otp, string $type): bool
    {
        $otpRecord = Otp::where('email', $email)
            ->where('otp', $otp)
            ->where('type', $type)
            ->where('used', false)
            ->first();

        if (!$otpRecord || $otpRecord->isExpired()) {
            return false;
        }

        return true;
    }

    public function refreshToken(User $user): array
    {
        $user->tokens()->delete();

        $tokenResult = $user->createToken('auth-token');
        $token = $tokenResult->plainTextToken;

        $tokenResult->accessToken->expires_at = now()->addDays(3);
        $tokenResult->accessToken->save();

        return [
            'token' => $token,
            'token_type' => 'Bearer'
        ];
    }

    public function getUserCompleteData(User $user): array
    {
        $user->load(['roles.permissions', 'permissions']);

        $userStores = $user->stores()->wherePivot('is_active', true)->get();

        $storeData = [];
        foreach ($userStores as $store) {
            $storePk = (int) $store->id;

            $storeRoles = $user->getRolesForStore($storePk);
            $effectiveRoles = $user->getEffectiveRolesForStore($storePk);
            $effectivePermissions = $user->getEffectivePermissionsForStore($storePk);

            $hierarchyInfo = $this->getStoreHierarchyForUser($user, $storePk, $storeRoles);

            $storeData[] = [
                'store' => [
                    'id' => (int) $store->id,
                    'store_id' => (string) $store->store_id,
                    'name' => $store->name,
                    'metadata' => $store->metadata,
                    'is_active' => (bool) $store->is_active
                ],
                'direct_roles' => $storeRoles->map(function ($role) {
                    return [
                        'id' => (int) $role->id,
                        'name' => (string) $role->name,
                        'guard_name' => (string) $role->guard_name,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => (int) $permission->id,
                                'name' => (string) $permission->name,
                                'guard_name' => (string) $permission->guard_name
                            ];
                        })
                    ];
                })->values(),
                'effective_roles' => $effectiveRoles->map(function ($role) use ($storeRoles) {
                    $isDirectRole = $storeRoles->contains('id', $role->id);
                    return [
                        'id' => (int) $role->id,
                        'name' => (string) $role->name,
                        'guard_name' => (string) $role->guard_name,
                        'is_inherited' => !$isDirectRole,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => (int) $permission->id,
                                'name' => (string) $permission->name,
                                'guard_name' => (string) $permission->guard_name
                            ];
                        })
                    ];
                })->values(),
                'effective_permissions' => $effectivePermissions->map(function ($permission) {
                    return [
                        'id' => (int) $permission->id,
                        'name' => (string) $permission->name,
                        'guard_name' => (string) $permission->guard_name
                    ];
                })->values(),
                'hierarchy_info' => $hierarchyInfo,
                'manageable_users' => $this->getManageableUsersInStore($user, $storePk),
                'assignment_metadata' => $this->getAssignmentMetadata($user, $storePk)
            ];
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,

            'global_roles' => $user->roles->map(function ($role) {
                return [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name,
                    'guard_name' => (string) $role->guard_name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => (int) $permission->id,
                            'name' => (string) $permission->name,
                            'guard_name' => (string) $permission->guard_name
                        ];
                    })
                ];
            })->values(),

            'global_permissions' => $user->permissions->map(function ($permission) {
                return [
                    'id' => (int) $permission->id,
                    'name' => (string) $permission->name,
                    'guard_name' => (string) $permission->guard_name
                ];
            })->values(),

            'all_permissions' => $this->getAllUserPermissions($user),

            'stores' => $storeData,

            'summary' => [
                'total_stores' => count($storeData),
                'total_roles' => $this->getTotalRolesCount($user),
                'total_permissions' => $this->getTotalPermissionsCount($user),
                'manageable_users_count' => $this->getTotalManageableUsersCount($user)
            ]
        ];
    }

    public function getStoreHierarchyForUser(User $user, int $storeId, $userRoles): array
    {
        $hierarchyInfo = [];

        foreach ($userRoles as $role) {
            $managedRoles = $role->lowerRolesInStore($storeId)->get();
            $managingRoles = $role->higherRolesInStore($storeId)->get();

            $hierarchyInfo[] = [
                'role' => [
                    'id' => (int) $role->id,
                    'name' => (string) $role->name
                ],
                'manages_roles' => $managedRoles->map(function ($managedRole) {
                    return [
                        'id' => (int) $managedRole->id,
                        'name' => (string) $managedRole->name
                    ];
                })->values(),
                'managed_by_roles' => $managingRoles->map(function ($managingRole) {
                    return [
                        'id' => (int) $managingRole->id,
                        'name' => (string) $managingRole->name
                    ];
                })->values(),
                'can_manage_users' => $managedRoles->count() > 0,
                'hierarchy_level' => $this->calculateHierarchyLevel($role, $storeId)
            ];
        }

        return $hierarchyInfo;
    }

    public function getManageableUsersInStore(User $user, int $storeId): array
    {
        $manageableUsers = [];
        $userRoles = $user->getRolesForStore($storeId);

        foreach ($userRoles as $role) {
            $managedRoles = $role->lowerRolesInStore($storeId)->get();

            foreach ($managedRoles as $managedRole) {
                $usersWithManagedRole = User::whereHas('roleTenancies', function ($q) use ($managedRole, $storeId) {
                    $q->where('role_id', (int) $managedRole->id)
                        ->where('store_id', (int) $storeId)
                        ->where('is_active', true);
                })->get();

                foreach ($usersWithManagedRole as $manageableUser) {
                    if ((int) $manageableUser->id !== (int) $user->id) {
                        $manageableUsers[] = [
                            'id' => (int) $manageableUser->id,
                            'name' => (string) $manageableUser->name,
                            'email' => (string) $manageableUser->email,
                            'role' => [
                                'id' => (int) $managedRole->id,
                                'name' => (string) $managedRole->name
                            ],
                            'can_edit' => true,
                            'can_remove' => true,
                            'management_level' => $this->getManagementLevel($role, $managedRole, $storeId)
                        ];
                    }
                }
            }
        }

        return collect($manageableUsers)->unique('id')->values()->all();
    }

    public function getAssignmentMetadata(User $user, int $storeId): array
    {
        return UserRoleStore::where('user_id', (int) $user->id)
            ->where('store_id', (int) $storeId)
            ->where('is_active', true)
            ->with('role')
            ->get()
            ->map(function ($assignment) {
                return [
                    'role_id' => (int) $assignment->role_id,
                    'role_name' => (string) $assignment->role->name,
                    'metadata' => $assignment->metadata,
                    'assigned_at' => $assignment->created_at,
                    'is_active' => (bool) $assignment->is_active
                ];
            })->all();
    }

    public function getAllUserPermissions(User $user): array
    {
        $allPermissions = collect();

        $allPermissions = $allPermissions->merge($user->getAllPermissions());

        $userStores = $user->stores()->wherePivot('is_active', true)->get();
        foreach ($userStores as $store) {
            $storePermissions = $user->getEffectivePermissionsForStore((int) $store->id);
            $allPermissions = $allPermissions->merge($storePermissions);
        }

        return $allPermissions->unique('id')->map(function ($permission) {
            return [
                'id' => (int) $permission->id,
                'name' => (string) $permission->name,
                'guard_name' => (string) $permission->guard_name
            ];
        })->values()->all();
    }

    public function getTotalRolesCount(User $user): int
    {
        $globalRoles = $user->roles->count();

        $storeRoles = UserRoleStore::where('user_id', (int) $user->id)
            ->where('is_active', true)
            ->distinct('role_id')
            ->count();

        return (int) ($globalRoles + $storeRoles);
    }

    public function getTotalPermissionsCount(User $user): int
    {
        return (int) count($this->getAllUserPermissions($user));
    }

    public function getTotalManageableUsersCount(User $user): int
    {
        $count = 0;
        $userStores = $user->stores()->wherePivot('is_active', true)->get();

        foreach ($userStores as $store) {
            $manageableUsers = $this->getManageableUsersInStore($user, (int) $store->id);
            $count += count($manageableUsers);
        }

        return (int) $count;
    }

    public function calculateHierarchyLevel(Role $role, int $storeId): int
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

            if ($level > 10) {
                break;
            }
        }

        return (int) $level;
    }

    public function getManagementLevel(Role $managerRole, Role $managedRole, int $storeId): string
    {
        if ($managerRole->lowerRolesInStore($storeId)->where('id', (int) $managedRole->id)->exists()) {
            return 'direct';
        }

        if ($managerRole->getAllLowerRolesForStore($storeId)->contains('id', (int) $managedRole->id)) {
            return 'indirect';
        }

        return 'none';
    }
}

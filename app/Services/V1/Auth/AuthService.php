<?php

namespace App\Services\V1\Auth;

use App\Models\User;
use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use App\Models\Role;
use App\Models\UserRoleStore;
use App\Models\AuthRule;
use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Services\AuthEvents\ModelChangeSet;
use App\Jobs\PublishOutboxEventJob;

class AuthService
{
    public function login(
        string $email,
        string $password,
        ?array $device = null,
        ?string $fcmToken = null,
        string $clientType = 'web',
        ?Request $request = null
    ): array {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Exception('Invalid credentials');
        }

        $tokenResult = $user->createToken('auth-token');
        $token = $tokenResult->plainTextToken;

        if ($clientType === 'mobile' || $device || $fcmToken) {
            $normalizedDevice = $this->upsertUserDevice($user, $device, $fcmToken);

            $this->emitUserDeviceUpsertedEvent(
                userId: (int) $user->id,
                device: $normalizedDevice,
                request: $request
            );
        }

        $userData = $this->getUserCompleteData($user);

        return [
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    public function impersonate(User $target, User $actor, ?Request $request = null): array
    {
        $tokenResult = $target->createToken('impersonation-token');
        $token = $tokenResult->plainTextToken;

        $this->recordEvent('auth.v1.user.impersonated', [
            'actor_id' => (int) $actor->id,
            'target_user_id' => (int) $target->id,
        ], $request);

        $userData = $this->getUserCompleteData($target);

        return [
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }

    private function emitUserDeviceUpsertedEvent(int $userId, array $device, ?Request $request = null): void
    {
        $this->recordEvent('auth.v1.user.device.upserted', [
            'user_id' => $userId,
            'device' => [
                'device_id' => data_get($device, 'device_id'),
                'platform' => data_get($device, 'platform'),
                'model' => data_get($device, 'model'),
                'os_version' => data_get($device, 'os_version'),
                'app_version' => data_get($device, 'app_version'),
                'fcm_token' => data_get($device, 'fcm_token'),
            ],
        ], $request);
    }

    private function recordEvent(string $subject, array $data, ?Request $request = null): void
    {
        $factory = app(AuthEventFactory::class);
        $outbox = app(AuthOutboxService::class);

        $envelope = $factory->make($subject, $data, $request);
        $row = $outbox->record($subject, $envelope);

        PublishOutboxEventJob::dispatch($row->id);
    }

    protected function upsertUserDevice(User $user, ?array $device, ?string $fcmToken): array
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
            $existing->refresh();

            return [
                'device_id' => $existing->device_id,
                'platform' => $existing->platform,
                'model' => $existing->model,
                'os_version' => $existing->os_version,
                'app_version' => $existing->app_version,
                'fcm_token' => $existing->fcm_token,
            ];
        }

        $created = $user->devices()->create($payload);
        $created->refresh();

        return [
            'device_id' => $created->device_id,
            'platform' => $created->platform,
            'model' => $created->model,
            'os_version' => $created->os_version,
            'app_version' => $created->app_version,
            'fcm_token' => $created->fcm_token,
        ];
    }

    public function sendOtp(string $email, string $type, ?Request $request = null): void
    {
        DB::transaction(function () use ($email, $type, $request) {
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

            $this->recordEvent('notifications.v1.email.send', [
                'template' => 'otp',
                'subject' => 'Your OTP Code',
                'users' => [
                    [
                        'email' => $email,
                        'data' => [
                            'otp' => $otpCode,
                            'type' => $type,
                        ],
                    ],
                ],
            ], $request);
        });
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

    public function updateMe(User $user, array $data, ?UploadedFile $imageFile = null, ?Request $request = null): array
    {
        $old = $user->replicate()->toArray();
        $storedPath = null;
        $oldImagePath = $user->image_path;
        $removeImage = !empty($data['remove_image'] ?? false);
        $deleteOldPath = null;

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (!empty($data['password'] ?? null)) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if ($imageFile) {
            $storedPath = Storage::disk('public')->putFile('avatars', $imageFile);
            $updateData['image_path'] = $storedPath;
            $deleteOldPath = $oldImagePath;
        } elseif ($removeImage) {
            $updateData['image_path'] = null;
            $deleteOldPath = $oldImagePath;
        }

        $user->update($updateData);
        $user->refresh();

        $changedFields = ModelChangeSet::fromArrays(
            $old,
            $user->toArray(),
            ['name', 'email', 'email_verified_at', 'image_path']
        );

        if (!empty($changedFields)) {
            $this->recordEvent('auth.v1.user.updated', [
                'user_id' => $user->id,
                'changed_fields' => $changedFields,
            ], $request);
        }

        if ($deleteOldPath && $deleteOldPath !== $storedPath) {
            if (!str_starts_with($deleteOldPath, 'http://') && !str_starts_with($deleteOldPath, 'https://')) {
                $diskPath = ltrim($deleteOldPath, '/');

                if (str_starts_with($diskPath, 'storage/')) {
                    $diskPath = substr($diskPath, strlen('storage/'));
                }

                Storage::disk('public')->delete($diskPath);
            }
        }

        return $this->getUserCompleteData($user);
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
            'image_path' => $user->image_path,
            'image_url' => $user->image_url,
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

    public function getAuthorizationOverview(User $user): array
    {
        $user->load([
            'roles.permissions',
            'permissions',
            'roleTenancies.role.permissions',
            'roleTenancies.store'
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1) Auth Rules
        |--------------------------------------------------------------------------
        */
        $authRules = AuthRule::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 2) Direct Roles (Global Roles - Not Store)
        |--------------------------------------------------------------------------
        */
        $directRoles = $user->roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'guard_name' => $perm->guard_name,
                ])->values(),
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | 3) Direct Permissions (assigned directly to user)
        |--------------------------------------------------------------------------
        */
        $directPermissions = $user->permissions->map(fn($perm) => [
            'id' => $perm->id,
            'name' => $perm->name,
            'guard_name' => $perm->guard_name,
        ])->values();

        /*
        |--------------------------------------------------------------------------
        | 4) Full Permissions (direct + via global roles)
        |--------------------------------------------------------------------------
        */
        $fullPermissions = $user->getAllPermissions()
            ->unique('id')
            ->map(fn($perm) => [
                'id' => $perm->id,
                'name' => $perm->name,
                'guard_name' => $perm->guard_name,
            ])->values();

        /*
        |--------------------------------------------------------------------------
        | 5) Store Assignments
        |--------------------------------------------------------------------------
        */
        $storeAssignments = [];

        foreach ($user->roleTenancies->where('is_active', true) as $assignment) {
            $storeId = $assignment->store->id;

            if (!isset($storeAssignments[$storeId])) {
                $storeAssignments[$storeId] = [
                    'store' => [
                        'id' => $assignment->store->id,
                        'name' => $assignment->store->name,
                        'metadata' => $assignment->store->metadata,
                        'is_active' => $assignment->store->is_active,
                    ],
                    'roles' => [],
                    'all_permissions_from_store_roles' => collect(),
                ];
            }

            $role = $assignment->role;

            // Role permissions in that store (with hierarchy)
            $rolePermissions = $role->getAllPermissionsForStore($storeId);

            $storeAssignments[$storeId]['roles'][] = [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'assignment_metadata' => $assignment->metadata,
                'permissions' => $rolePermissions->map(fn($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'guard_name' => $perm->guard_name,
                ])->values(),
            ];

            $storeAssignments[$storeId]['all_permissions_from_store_roles'] =
                $storeAssignments[$storeId]['all_permissions_from_store_roles']
                    ->merge($rolePermissions);
        }

        // Normalize store permissions
        $storeAssignments = collect($storeAssignments)->map(function ($store) {
            $store['all_permissions_from_store_roles'] =
                $store['all_permissions_from_store_roles']
                    ->unique('id')
                    ->map(fn($perm) => [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'guard_name' => $perm->guard_name,
                    ])
                    ->values();

            return $store;
        })->values();

        /*
        |--------------------------------------------------------------------------
        | Final Structured Response
        |--------------------------------------------------------------------------
        */
        return [
            'auth_rules' => $authRules,

            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],

            'direct_roles' => $directRoles,

            'direct_permissions' => $directPermissions,

            'full_permissions' => $fullPermissions,

            'store_assignments' => $storeAssignments,
        ];
    }
}

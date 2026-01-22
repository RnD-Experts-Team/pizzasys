<?php

namespace App\Services\V1\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\UserRoleStore;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Services\AuthEvents\ModelChangeSet;
use App\Jobs\PublishAuthOutboxEventJob;

class UserManagementService
{
    public function getAllUsers($perPage = 15, $search = null, $role = null)
    {
        $query = User::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('id', $role)->orWhere('name', $role);
            });
        }

        $users = $query->with([
            'roles.permissions',
            'permissions'
        ])->paginate($perPage);

        $users->getCollection()->transform(function ($user) {
            $storeData = $this->getUserStores($user->id);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,

                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name
                            ];
                        })
                    ];
                }),

                'permissions' => $user->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name
                    ];
                }),

                'stores' => $storeData
            ];
        });

        return $users;
    }

    private function getUserStores($userId)
    {
        $assignments = UserRoleStore::where('user_id', $userId)
            ->where('is_active', true)
            ->with(['role.permissions', 'store'])
            ->get();

        $storeGroups = $assignments->groupBy('store_id');
        $stores = [];

        foreach ($storeGroups as $storeId => $storeAssignments) {
            $store = $storeAssignments->first()->store;
            $roles = [];

            foreach ($storeAssignments as $assignment) {
                $roles[] = [
                    'id' => $assignment->role->id,
                    'name' => $assignment->role->name,
                    'permissions' => $assignment->role->permissions->map(function ($permission) {
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

            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name
                        ];
                    })
                ];
            }),

            'permissions' => $user->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name
                ];
            }),

            'stores' => $this->getUserStores($user->id)
        ];
    }

    /**
     * Record event to outbox and dispatch publish job AFTER COMMIT.
     */
    private function recordEvent(string $subject, array $data, ?Request $request = null): void
    {
        $factory = app(AuthEventFactory::class);
        $outbox  = app(AuthOutboxService::class);

        $envelope = $factory->make($subject, $data, $request);
        $row = $outbox->record($subject, $envelope);

        DB::afterCommit(fn() => PublishAuthOutboxEventJob::dispatch($row->id));
    }

    private function diffAddedRemoved(array $from, array $to): array
    {
        $from = array_values(array_unique($from));
        $to   = array_values(array_unique($to));

        $added = array_values(array_diff($to, $from));
        $removed = array_values(array_diff($from, $to));

        return [
            'from' => $from,
            'to' => $to,
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * ✅ FULL SNAPSHOT EVENT on create only.
     */
    public function createUser(array $data, ?Request $request = null): User
    {
        return DB::transaction(function () use ($data, $request) {

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            if (isset($data['roles'])) {
                $user->assignRole($data['roles']);
            }

            if (isset($data['permissions'])) {
                $user->givePermissionTo($data['permissions']);
            }

            // Load only what we need once (this is also what you return)
            $user = $user->fresh()->load(['roles.permissions', 'permissions']);

            // FULL SNAPSHOT (created)
            $this->recordEvent('auth.v1.user.created', [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => optional($user->email_verified_at)?->toIso8601String(),
                    'created_at' => optional($user->created_at)?->toIso8601String(),
                    'updated_at' => optional($user->updated_at)?->toIso8601String(),
                ],
                'roles' => $user->roles->pluck('name')->values()->toArray(),
                'permissions_direct' => $user->permissions->pluck('name')->values()->toArray(),
            ], $request);

            // If you want these as deltas too (optional but consistent)
            if (isset($data['roles'])) {
                $this->recordEvent('auth.v1.user.role.assigned', [
                    'user_id' => $user->id,
                    'roles' => array_values($data['roles']),
                ], $request);
            }

            if (isset($data['permissions'])) {
                $this->recordEvent('auth.v1.user.permission.granted', [
                    'user_id' => $user->id,
                    'permissions' => array_values($data['permissions']),
                ], $request);
            }

            return $user->getWithRolesAndPermissions();
        });
    }

    /**
     * ✅ DELTA ONLY for user updated.
     */
    public function updateUser(User $user, array $data, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $data, $request) {

            // Snapshot BEFORE updates (this is the reliable "from" state)
            $old = $user->replicate()->toArray();

            $updateData = [];

            if (array_key_exists('name', $data)) {
                $updateData['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $updateData['email'] = $data['email'];
            }

            if (!empty($data['password'] ?? null)) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Roles sync => publish role.sync DELTA
            if (isset($data['roles'])) {
                $before = $user->roles()->pluck('name')->values()->toArray();
                $user->syncRoles($data['roles']);
                $after  = $user->fresh()->roles()->pluck('name')->values()->toArray();

                $diff = $this->diffAddedRemoved($before, $after);

                $this->recordEvent('auth.v1.user.role.synced', [
                    'user_id' => $user->id,
                    'roles' => $diff,
                ], $request);
            }

            // Permissions sync => publish permission.sync DELTA
            if (isset($data['permissions'])) {
                $before = $user->permissions()->pluck('name')->values()->toArray();
                $user->syncPermissions($data['permissions']);
                $after  = $user->fresh()->permissions()->pluck('name')->values()->toArray();

                $diff = $this->diffAddedRemoved($before, $after);

                $this->recordEvent('auth.v1.user.permission.synced', [
                    'user_id' => $user->id,
                    'permissions' => $diff,
                ], $request);
            }

            // Snapshot AFTER updates (this is the reliable "to" state)
            $fresh = $user->fresh();
            $new = $fresh->toArray();

            // Compute changed fields from snapshots (reliable from/to)
            $fieldChanges = ModelChangeSet::fromArrays(
                $old,
                $new,
                ['name', 'email', 'email_verified_at'] // DO NOT include password in events
            );

            if (!empty($fieldChanges)) {
                $this->recordEvent('auth.v1.user.updated', [
                    'user_id' => $user->id,
                    'changed_fields' => $fieldChanges,
                ], $request);
            }

            return $fresh->getWithRolesAndPermissions();
        });
    }


    public function deleteUser(User $user, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($user, $request) {

            $userId = $user->id;
            $email = $user->email;

            $user->tokens()->delete();
            $deleted = $user->delete();

            $this->recordEvent('auth.v1.user.deleted', [
                'user_id' => $userId,
                'email' => $email,
                'deleted_at' => now()->utc()->toIso8601String(),
            ], $request);

            return $deleted;
        });
    }

    public function assignRolesToUser(User $user, array $roles, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $roles, $request) {
            $user->assignRole($roles);

            $this->recordEvent('auth.v1.user.role.assigned', [
                'user_id' => $user->id,
                'roles' => array_values($roles),
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }

    public function removeRolesFromUser(User $user, array $roles, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $roles, $request) {
            foreach ($roles as $role) {
                $user->removeRole($role);
            }

            $this->recordEvent('auth.v1.user.role.removed', [
                'user_id' => $user->id,
                'roles' => array_values($roles),
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }

    public function syncUserRoles(User $user, array $roles, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $roles, $request) {
            $before = $user->roles()->pluck('name')->values()->toArray();
            $user->syncRoles($roles);
            $after  = $user->fresh()->roles()->pluck('name')->values()->toArray();

            $diff = $this->diffAddedRemoved($before, $after);

            $this->recordEvent('auth.v1.user.role.synced', [
                'user_id' => $user->id,
                'roles' => $diff,
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }

    public function givePermissionsToUser(User $user, array $permissions, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $permissions, $request) {
            $user->givePermissionTo($permissions);

            $this->recordEvent('auth.v1.user.permission.granted', [
                'user_id' => $user->id,
                'permissions' => array_values($permissions),
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }

    public function revokePermissionsFromUser(User $user, array $permissions, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $permissions, $request) {
            foreach ($permissions as $permission) {
                $user->revokePermissionTo($permission);
            }

            $this->recordEvent('auth.v1.user.permission.revoked', [
                'user_id' => $user->id,
                'permissions' => array_values($permissions),
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }

    public function syncUserPermissions(User $user, array $permissions, ?Request $request = null): User
    {
        return DB::transaction(function () use ($user, $permissions, $request) {
            $before = $user->permissions()->pluck('name')->values()->toArray();
            $user->syncPermissions($permissions);
            $after  = $user->fresh()->permissions()->pluck('name')->values()->toArray();

            $diff = $this->diffAddedRemoved($before, $after);

            $this->recordEvent('auth.v1.user.permission.synced', [
                'user_id' => $user->id,
                'permissions' => $diff,
            ], $request);

            return $user->fresh()->getWithRolesAndPermissions();
        });
    }
}

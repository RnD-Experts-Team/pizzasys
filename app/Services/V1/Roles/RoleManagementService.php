<?php

namespace App\Services\V1\Roles;

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Services\AuthEvents\ModelChangeSet;
use App\Jobs\PublishAuthOutboxEventJob;

class RoleManagementService
{
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

    public function getAllRoles($perPage = 15, $search = null)
    {
        $query = Role::with('permissions');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * Events:
     * - auth.v1.role.created (snapshot)
     * - (optional) auth.v1.assignment.role_permission.assigned (if permissions passed)
     */
    public function createRole(array $data, ?Request $request = null): Role
    {
        return DB::transaction(function () use ($data, $request) {

            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'web',
            ]);

            $assignedPermissions = [];

            if (isset($data['permissions']) && is_array($data['permissions']) && count($data['permissions']) > 0) {
                $role->givePermissionTo($data['permissions']);
                $assignedPermissions = array_values($data['permissions']);
            }

            // Load once for response + snapshot
            $role->load('permissions');

            // Snapshot event: include full details + permissions list (this is the "full scale" case)
            $this->recordEvent('auth.v1.role.created', [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'guard_name' => $p->guard_name,
                    ])->values()->all(),
                    'created_at' => optional($role->created_at)?->toIso8601String(),
                    'updated_at' => optional($role->updated_at)?->toIso8601String(),
                ],
            ], $request);

            // Optional: also emit relationship assigned event (useful for consumers that only track assignments)
            if (count($assignedPermissions) > 0) {
                $this->recordEvent('auth.v1.assignment.role_permission.assigned', [
                    'role_id' => $role->id,
                    'permissions' => $assignedPermissions,
                    'assigned_at' => now()->utc()->toIso8601String(),
                    'source' => 'create_role',
                ], $request);
            }

            return $role;
        });
    }

    /**
     * Events:
     * - auth.v1.role.updated (delta only for role fields)
     * - auth.v1.assignment.role_permission.synced (if permissions provided)
     */
    public function updateRole(Role $role, array $data, ?Request $request = null): Role
    {
        return DB::transaction(function () use ($role, $data, $request) {

            $roleId = $role->id;

            // Capture old state WITHOUT extra DB calls
            $oldRole = $role->replicate()->toArray();

            // Only fetch old permissions list if we are going to sync
            $oldPermNames = null;
            if (array_key_exists('permissions', $data)) {
                $oldPermNames = $role->permissions()->pluck('name')->values()->all();
            }

            // Update role fields (delta)
            if (isset($data['name'])) {
                $role->update(['name' => $data['name']]);
            }

            // Sync permissions (relationship delta)
            if (array_key_exists('permissions', $data)) {
                $newPermNames = is_array($data['permissions']) ? array_values($data['permissions']) : [];
                $role->syncPermissions($newPermNames);

                // Compute added/removed for a clean relationship event
                $added = array_values(array_diff($newPermNames, $oldPermNames ?? []));
                $removed = array_values(array_diff($oldPermNames ?? [], $newPermNames));

                // Always emit synced event if updateRole included permissions (even if no diff),
                // because the command itself matters for audit in distributed systems.
                $this->recordEvent('auth.v1.assignment.role_permission.synced', [
                    'role_id' => $roleId,
                    'added' => $added,
                    'removed' => $removed,
                    'final' => $newPermNames,
                    'synced_at' => now()->utc()->toIso8601String(),
                    'source' => 'update_role',
                ], $request);
            }

            $role = $role->fresh();
            $changedFields = ModelChangeSet::fromArrays(
                $oldRole,
                $role->toArray(),
                ['name', 'guard_name']
            );

            if (!empty($changedFields)) {
                $this->recordEvent('auth.v1.role.updated', [
                    'role_id' => $roleId,
                    'changed_fields' => $changedFields,
                    'updated_at' => optional($role->updated_at)?->toIso8601String(),
                ], $request);
            }

            return $role->load('permissions');
        });
    }

    /**
     * Event:
     * - auth.v1.role.deleted (minimal)
     */
    public function deleteRole(Role $role, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($role, $request) {

            $roleId = $role->id;
            $roleName = $role->name;
            $guard = $role->guard_name;

            $deleted = $role->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.role.deleted', [
                    'role_id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => $guard,
                    'deleted_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return (bool) $deleted;
        });
    }

    /**
     * Event:
     * - auth.v1.assignment.role_permission.assigned (delta only)
     */
    public function assignPermissionsToRole(Role $role, array $permissions, ?Request $request = null): Role
    {
        return DB::transaction(function () use ($role, $permissions, $request) {

            $roleId = $role->id;
            $permNames = array_values($permissions);

            $role->givePermissionTo($permNames);

            $this->recordEvent('auth.v1.assignment.role_permission.assigned', [
                'role_id' => $roleId,
                'permissions' => $permNames,
                'assigned_at' => now()->utc()->toIso8601String(),
                'source' => 'assign_permissions_to_role',
            ], $request);

            return $role->load('permissions');
        });
    }

    /**
     * Event:
     * - auth.v1.assignment.role_permission.revoked (delta only)
     */
    public function removePermissionsFromRole(Role $role, array $permissions, ?Request $request = null): Role
    {
        return DB::transaction(function () use ($role, $permissions, $request) {

            $roleId = $role->id;
            $permNames = array_values($permissions);

            $role->revokePermissionTo($permNames);

            $this->recordEvent('auth.v1.assignment.role_permission.revoked', [
                'role_id' => $roleId,
                'permissions' => $permNames,
                'revoked_at' => now()->utc()->toIso8601String(),
                'source' => 'remove_permissions_from_role',
            ], $request);

            return $role->load('permissions');
        });
    }

    /**
     * Event:
     * - auth.v1.assignment.role_permission.synced (delta + final list)
     */
    public function syncRolePermissions(Role $role, array $permissions, ?Request $request = null): Role
    {
        return DB::transaction(function () use ($role, $permissions, $request) {

            $roleId = $role->id;

            $oldPermNames = $role->permissions()->pluck('name')->values()->all();
            $newPermNames = array_values($permissions);

            $role->syncPermissions($newPermNames);

            $added = array_values(array_diff($newPermNames, $oldPermNames));
            $removed = array_values(array_diff($oldPermNames, $newPermNames));

            $this->recordEvent('auth.v1.assignment.role_permission.synced', [
                'role_id' => $roleId,
                'added' => $added,
                'removed' => $removed,
                'final' => $newPermNames,
                'synced_at' => now()->utc()->toIso8601String(),
                'source' => 'sync_role_permissions',
            ], $request);

            return $role->load('permissions');
        });
    }
}

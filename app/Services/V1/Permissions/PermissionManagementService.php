<?php

namespace App\Services\V1\Permissions;

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Services\AuthEvents\ModelChangeSet;
use App\Jobs\PublishAuthOutboxEventJob;

class PermissionManagementService
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

    public function getAllPermissions($perPage = 15, $search = null)
    {
        $query = Permission::with('roles');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * Event: auth.v1.permission.created (snapshot)
     */
    public function createPermission(array $data, ?Request $request = null): Permission
    {
        return DB::transaction(function () use ($data, $request) {

            $permission = Permission::create([
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'web',
            ]);

            $this->recordEvent('auth.v1.permission.created', [
                'permission' => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => optional($permission->created_at)?->toIso8601String(),
                    'updated_at' => optional($permission->updated_at)?->toIso8601String(),
                ],
            ], $request);

            return $permission;
        });
    }

    /**
     * Event: auth.v1.permission.updated (delta)
     */
    public function updatePermission(Permission $permission, array $data, ?Request $request = null): Permission
    {
        return DB::transaction(function () use ($permission, $data, $request) {

            $permissionId = $permission->id;

            // capture old state (cheap, no DB hit)
            $old = $permission->replicate()->toArray();

            $permission->update([
                'name' => $data['name'],
            ]);

            $permission = $permission->fresh();

            $changed = ModelChangeSet::fromArrays(
                $old,
                $permission->toArray(),
                ['name', 'guard_name']
            );

            if (!empty($changed)) {
                $this->recordEvent('auth.v1.permission.updated', [
                    'permission_id' => $permissionId,
                    'changed_fields' => $changed,
                    'updated_at' => optional($permission->updated_at)?->toIso8601String(),
                ], $request);
            }

            return $permission;
        });
    }

    /**
     * Event: auth.v1.permission.deleted (minimal)
     */
    public function deletePermission(Permission $permission, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($permission, $request) {

            $permissionId = $permission->id;
            $permissionName = $permission->name;
            $guard = $permission->guard_name;

            $deleted = $permission->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.permission.deleted', [
                    'permission_id' => $permissionId,
                    'name' => $permissionName,
                    'guard_name' => $guard,
                    'deleted_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return $deleted;
        });
    }

    public function getPermissionsByGuard(string $guard = 'web')
    {
        return Permission::where('guard_name', $guard)->get();
    }
}

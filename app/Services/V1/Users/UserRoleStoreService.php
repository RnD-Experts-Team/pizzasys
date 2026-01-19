<?php

namespace App\Services\V1\Users;

use App\Models\UserRoleStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Jobs\PublishAuthOutboxEventJob;

class UserRoleStoreService
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

    /**
     * Assign (create) a user-role-store relationship.
     * Event: auth.v1.assignment.user_role_store.assigned
     */
    public function assignUserRoleStore(array $data, ?Request $request = null): UserRoleStore
    {
        return DB::transaction(function () use ($data, $request) {

            $assignment = UserRoleStore::create([
                'user_id' => $data['user_id'],
                'role_id' => $data['role_id'],
                'store_id' => $data['store_id'],
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->recordEvent('auth.v1.assignment.user_role_store.assigned', [
                'assignment' => [
                    'id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'role_id' => $assignment->role_id,
                    'store_id' => $assignment->store_id,
                    'metadata' => $assignment->metadata,
                    'is_active' => (bool) $assignment->is_active,
                    'created_at' => optional($assignment->created_at)?->toIso8601String(),
                    'updated_at' => optional($assignment->updated_at)?->toIso8601String(),
                ],
            ], $request);

            return $assignment;
        });
    }

    /**
     * Remove (delete) a user-role-store relationship.
     * Event: auth.v1.assignment.user_role_store.removed
     */
    public function removeUserRoleStore(int $userId, int $roleId, string $storeId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($userId, $roleId, $storeId, $request) {

            // Grab assignment id (optional) before delete for better traceability
            $assignment = UserRoleStore::where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->first();

            $deleted = UserRoleStore::where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.assignment.user_role_store.removed', [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'store_id' => $storeId,
                    'assignment_id' => $assignment?->id,
                    'removed_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return (bool) $deleted;
        });
    }

    /**
     * Toggle is_active for an assignment.
     * Event: auth.v1.assignment.user_role_store.toggled
     */
    public function toggleUserRoleStore(int $userId, int $roleId, string $storeId, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($userId, $roleId, $storeId, $request) {

            $assignment = UserRoleStore::where('user_id', $userId)
                ->where('role_id', $roleId)
                ->where('store_id', $storeId)
                ->first();

            if (!$assignment) {
                return false;
            }

            $before = (bool) $assignment->is_active;
            $after  = !$before;

            $assignment->update(['is_active' => $after]);

            $this->recordEvent('auth.v1.assignment.user_role_store.toggled', [
                'assignment_id' => $assignment->id,
                'user_id' => $userId,
                'role_id' => $roleId,
                'store_id' => $storeId,
                'before_is_active' => $before,
                'after_is_active' => $after,
                'toggled_at' => now()->utc()->toIso8601String(),
            ], $request);

            return true;
        });
    }

    public function getUserRoleStoreAssignments(int $userId, string $storeId = null)
    {
        $query = UserRoleStore::where('user_id', $userId)
            ->with(['role', 'store']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    public function getStoreRoleAssignments(string $storeId, int $roleId = null)
    {
        $query = UserRoleStore::where('store_id', $storeId)
            ->with(['user', 'role']);

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        return $query->get();
    }

    /**
     * Bulk assignment.
     * Event: auth.v1.assignment.user_role_store.bulk_assigned (single batch event)
     *
     * If you prefer 1 event per row as well, tell me and I’ll add it (still safe).
     */
    public function bulkAssignUserRoleStore(int $userId, array $assignments, ?Request $request = null): array
    {
        return DB::transaction(function () use ($userId, $assignments, $request) {

            $results = [];

            foreach ($assignments as $assignment) {
                $results[] = UserRoleStore::create([
                    'user_id' => $userId,
                    'role_id' => $assignment['role_id'],
                    'store_id' => $assignment['store_id'],
                    'metadata' => $assignment['metadata'] ?? null,
                    'is_active' => $assignment['is_active'] ?? true,
                ]);
            }

            $this->recordEvent('auth.v1.assignment.user_role_store.bulk_assigned', [
                'user_id' => $userId,
                'count' => count($results),
                'assignments' => array_map(function ($row) {
                    /** @var \App\Models\UserRoleStore $row */
                    return [
                        'id' => $row->id,
                        'role_id' => $row->role_id,
                        'store_id' => $row->store_id,
                        'metadata' => $row->metadata,
                        'is_active' => (bool) $row->is_active,
                        'created_at' => optional($row->created_at)?->toIso8601String(),
                    ];
                }, $results),
            ], $request);

            return $results;
        });
    }
}

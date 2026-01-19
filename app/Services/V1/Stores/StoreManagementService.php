<?php

namespace App\Services\V1\Stores;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Services\AuthEvents\AuthEventFactory;
use App\Services\AuthEvents\AuthOutboxService;
use App\Services\AuthEvents\ModelChangeSet;
use App\Jobs\PublishAuthOutboxEventJob;

class StoreManagementService
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

    public function getAllStores($perPage = 15, $search = null)
    {
        $query = Store::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Event: auth.v1.store.created (snapshot)
     */
    public function createStore(array $data, ?Request $request = null): Store
    {
        return DB::transaction(function () use ($data, $request) {

            $store = Store::create([
                'id' => $data['id'],
                'name' => $data['name'],
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->recordEvent('auth.v1.store.created', [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'metadata' => $store->metadata,
                    'is_active' => (bool) $store->is_active,
                    'created_at' => optional($store->created_at)?->toIso8601String(),
                    'updated_at' => optional($store->updated_at)?->toIso8601String(),
                ],
            ], $request);

            return $store;
        });
    }

    /**
     * Event: auth.v1.store.updated (delta)
     */
    public function updateStore(Store $store, array $data, ?Request $request = null): Store
    {
        return DB::transaction(function () use ($store, $data, $request) {

            $storeId = $store->id;

            // Capture old values before update for delta reporting
            $old = $store->replicate()->toArray();

            $store->update($data);
            $store = $store->fresh();

            // Compute delta (only the fields we allow to change)
            $changed = ModelChangeSet::fromArrays(
                $old,
                $store->toArray(),
                ['name', 'metadata', 'is_active']
            );

            if (!empty($changed)) {
                $this->recordEvent('auth.v1.store.updated', [
                    'store_id' => $storeId,
                    'changed_fields' => $changed,
                    'updated_at' => optional($store->updated_at)?->toIso8601String(),
                ], $request);
            }

            return $store;
        });
    }

    /**
     * Event: auth.v1.store.deleted (minimal)
     */
    public function deleteStore(Store $store, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($store, $request) {

            $storeId = $store->id;
            $storeName = $store->name;

            $deleted = $store->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.store.deleted', [
                    'store_id' => $storeId,
                    'store_name' => $storeName,
                    'deleted_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return $deleted;
        });
    }

    public function getStoreUsers(Store $store, $roleId = null)
    {
        $query = $store->users();

        if ($roleId) {
            $query->wherePivot('role_id', $roleId);
        }

        return $query->wherePivot('is_active', true)->get();
    }

    public function getStoreRoles(Store $store, $userId = null)
    {
        $query = $store->roles();

        if ($userId) {
            $query->wherePivot('user_id', $userId);
        }

        return $query->wherePivot('is_active', true)->get();
    }

    public function getUserAccessibleStores(User $user)
    {
        return $user->stores()->wherePivot('is_active', true)->get();
    }
}

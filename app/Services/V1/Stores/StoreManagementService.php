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
                    ->orWhere('store_id', 'like', "%{$search}%"); // manual store_id search
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function createStore(array $data, ?Request $request = null): Store
    {
        return DB::transaction(function () use ($data, $request) {

            $store = Store::create([
                'store_id' => $data['store_id'],
                'name' => $data['name'],
                'metadata' => $data['metadata'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->recordEvent('auth.v1.store.created', [
                'store' => [
                    'id' => (int) $store->id,                 // numeric PK
                    'store_id' => (string) $store->store_id,  // manual string id
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

    public function updateStore(Store $store, array $data, ?Request $request = null): Store
    {
        return DB::transaction(function () use ($store, $data, $request) {

            $storePk = (int) $store->id;

            // Snapshot BEFORE update
            $old = $store->replicate()->toArray();

            // Only allow fields you actually want to track/update
            $updateData = [];
            foreach (['name', 'metadata', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($updateData)) {
                $store->update($updateData);
            }

            $store = $store->fresh();

            // Snapshot AFTER update
            $new = $store->toArray();

            $changed = ModelChangeSet::fromArrays(
                $old,
                $new,
                ['name', 'metadata', 'is_active']
            );

            if (!empty($changed)) {
                $this->recordEvent('auth.v1.store.updated', [
                    'store_id' => $storePk,
                    'changed_fields' => $changed,
                    'updated_at' => optional($store->updated_at)?->toIso8601String(),
                ], $request);
            }

            return $store;
        });
    }


    public function deleteStore(Store $store, ?Request $request = null): bool
    {
        return DB::transaction(function () use ($store, $request) {

            $storePk = (int) $store->id;

            $deleted = $store->delete();

            if ($deleted) {
                $this->recordEvent('auth.v1.store.deleted', [
                    'store_id' => $storePk,
                    'store_name' => $store->name,
                    'deleted_at' => now()->utc()->toIso8601String(),
                ], $request);
            }

            return (bool) $deleted;
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

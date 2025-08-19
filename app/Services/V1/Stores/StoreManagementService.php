<?php

namespace App\Services\V1\Stores;

use App\Models\Store;
use App\Models\User;
use App\Models\Role;

class StoreManagementService
{
    public function getAllStores($perPage = 15, $search = null)
    {
        $query = Store::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function createStore(array $data): Store
    {
        return Store::create([
            'id' => $data['id'],
            'name' => $data['name'],
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function updateStore(Store $store, array $data): Store
    {
        $store->update(array_filter($data));
        return $store->fresh();
    }

    public function deleteStore(Store $store): bool
    {
        return $store->delete();
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

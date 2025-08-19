<?php

namespace App\Services\V1\Users;

use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\UserRoleStore;

class UserRoleStoreService
{
    public function assignUserRoleStore(array $data): UserRoleStore
    {
        return UserRoleStore::create([
            'user_id' => $data['user_id'],
            'role_id' => $data['role_id'],
            'store_id' => $data['store_id'],
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function removeUserRoleStore(int $userId, int $roleId, string $storeId): bool
    {
        return UserRoleStore::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->where('store_id', $storeId)
            ->delete();
    }

    public function toggleUserRoleStore(int $userId, int $roleId, string $storeId): bool
    {
        $assignment = UserRoleStore::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->where('store_id', $storeId)
            ->first();

        if ($assignment) {
            $assignment->update(['is_active' => !$assignment->is_active]);
            return true;
        }

        return false;
    }

    public function getUserRoleStoreAssignments(int $userId, string $storeId = null)
    {
        $query = UserRoleStore::where('user_id', $userId)
            ->with(['role', 'store']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->where('is_active', true)->get();
    }

    public function getStoreRoleAssignments(string $storeId, int $roleId = null)
    {
        $query = UserRoleStore::where('store_id', $storeId)
            ->with(['user', 'role']);

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        return $query->where('is_active', true)->get();
    }

    public function bulkAssignUserRoleStore(int $userId, array $assignments): array
    {
        $results = [];
        
        foreach ($assignments as $assignment) {
            $results[] = $this->assignUserRoleStore([
                'user_id' => $userId,
                'role_id' => $assignment['role_id'],
                'store_id' => $assignment['store_id'],
                'metadata' => $assignment['metadata'] ?? null,
                'is_active' => $assignment['is_active'] ?? true,
            ]);
        }
        
        return $results;
    }
}

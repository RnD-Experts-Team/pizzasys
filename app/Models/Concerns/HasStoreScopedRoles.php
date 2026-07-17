<?php

namespace App\Models\Concerns;

use App\Models\Role;
use App\Models\Store;

/**
 * Store-scoped role helpers for User (user_role_store pivot). The pivot FK
 * (user_id) is derived automatically from the model's getForeignKey().
 *
 * Note: employees do NOT use this trait — they have no store-scoped roles;
 * their store dimension is active membership in employee_stores.
 */
trait HasStoreScopedRoles
{
    abstract protected function roleStorePivotTable(): string;

    public function storeRoles()
    {
        return $this->belongsToMany(Role::class, $this->roleStorePivotTable())
            ->withPivot('store_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, $this->roleStorePivotTable())
            ->withPivot('role_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function getRolesForStore(int $storeId)
    {
        return $this->storeRoles()
            ->wherePivot('store_id', (int) $storeId)
            ->wherePivot('is_active', true)
            ->get();
    }

    public function getEffectiveRolesForStore(int $storeId)
    {
        $directRoles = $this->getRolesForStore($storeId);
        $allRoles = collect($directRoles->all());

        foreach ($directRoles as $role) {
            $inheritedRoles = $role->getAllLowerRolesForStore($storeId);
            $allRoles = $allRoles->merge($inheritedRoles);
        }

        return $allRoles->unique('id');
    }

    public function getEffectivePermissionsForStore(int $storeId)
    {
        $allRoles = $this->getEffectiveRolesForStore($storeId);
        $allPermissions = collect();

        foreach ($allRoles as $role) {
            $allPermissions = $allPermissions->merge($role->permissions);
        }

        return $allPermissions->unique('id');
    }

    public function hasPermissionInStore(string $permission, string $storeId): bool
    {
        return $this->getEffectivePermissionsForStore($storeId)->contains('name', $permission);
    }
}

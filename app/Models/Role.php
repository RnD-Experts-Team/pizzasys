<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends SpatieRole
{
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'user_role_store')
                    ->withPivot('user_id', 'metadata', 'is_active')
                    ->withTimestamps();
    }

    public function usersInStores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role_store')
                    ->withPivot('store_id', 'metadata', 'is_active')
                    ->withTimestamps();
    }

    public function usersForStore(Store $store)
    {
        return $this->usersInStores()->wherePivot('store_id', $store->id)
                                     ->wherePivot('is_active', true);
    }

    public function lowerRolesInStore(string $storeId)
    {
        return $this->belongsToMany(Role::class, 'role_hierarchy', 'higher_role_id', 'lower_role_id')
                    ->wherePivot('store_id', $storeId)
                    ->wherePivot('is_active', true)
                    ->withTimestamps();
    }

    public function higherRolesInStore(string $storeId)
    {
        return $this->belongsToMany(Role::class, 'role_hierarchy', 'lower_role_id', 'higher_role_id')
                    ->wherePivot('store_id', $storeId)
                    ->wherePivot('is_active', true)
                    ->withTimestamps();
    }

    public function getAllLowerRolesForStore(string $storeId, array &$visited = []): \Illuminate\Support\Collection
    {
        if (in_array($this->id, $visited)) {
            return collect();
        }
        
        $visited[] = $this->id;
        $directLower = $this->lowerRolesInStore($storeId)->get();
        $allLower = collect($directLower->all());
        
        foreach ($directLower as $lowerRole) {
            $allLower = $allLower->merge($lowerRole->getAllLowerRolesForStore($storeId, $visited));
        }
        
        return $allLower->unique('id');
    }

    public function isHigherThan(Role $otherRole, string $storeId): bool
    {
        return $this->getAllLowerRolesForStore($storeId)->contains('id', $otherRole->id);
    }

    public function getAllPermissionsForStore(string $storeId): \Illuminate\Support\Collection
    {
        $allRoles = collect([$this])->merge($this->getAllLowerRolesForStore($storeId));
        $allPermissions = collect();
        
        foreach ($allRoles as $role) {
            $allPermissions = $allPermissions->merge($role->permissions);
        }
        
        return $allPermissions->unique('id');
    }
}

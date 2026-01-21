<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Store;
use App\Models\UserDevice;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getWithRolesAndPermissions()
    {
        return $this->load(['roles', 'permissions', 'roles.permissions']);
    }

    public function getWithRolesPermissionsAndStores(): User
    {
        return $this->load([
            'roles',
            'permissions',
            'roles.permissions',
            'roleTenancies.role',
            'roleTenancies.store',
        ]);
    }

    public function getRolePermissions()
    {
        $rolePermissions = collect();
        foreach ($this->roles as $role) {
            $rolePermissions = $rolePermissions->merge($role->permissions);
        }
        return $rolePermissions->unique('id')->values();
    }

    /**
     * Returns stores grouped with roles based on the UserRoleStore pivot model.
     */
    public function getStoresWithRoles()
    {
        $storeRolesMap = [];

        foreach ($this->roleTenancies as $assignment) {
            if (!$assignment->store || !$assignment->role) {
                continue;
            }

            $storePk = (int) $assignment->store->id;

            if (!isset($storeRolesMap[$storePk])) {
                $storeRolesMap[$storePk] = [
                    'store' => [
                        'id' => (int) $assignment->store->id,
                        'store_id' => (string) $assignment->store->store_id,
                        'name' => (string) $assignment->store->name,
                        'metadata' => $assignment->store->metadata,
                        'is_active' => (bool) $assignment->store->is_active,
                    ],
                    'roles' => [],
                ];
            }

            $storeRolesMap[$storePk]['roles'][] = [
                'id' => (int) $assignment->role->id,
                'name' => (string) $assignment->role->name,
                'guard_name' => (string) $assignment->role->guard_name,
                'assignment_metadata' => $assignment->metadata,
                'is_active' => (bool) $assignment->is_active,
                'assigned_at' => $assignment->created_at,
            ];
        }

        return array_values($storeRolesMap);
    }

    public function getRoleNames()
    {
        return $this->roles->pluck('name');
    }

    public function getAllPermissions()
    {
        return $this->getPermissionsViaRoles()->merge($this->permissions);
    }

    public function storeRoles()
    {
        return $this->belongsToMany(Role::class, 'user_role_store')
            ->withPivot('store_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'user_role_store')
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

    public function getStoresForRole(Role $role)
    {
        return $this->stores()
            ->wherePivot('role_id', (int) $role->id)
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

    public function canActOnUserInStore(User $targetUser, int $storeId): bool
    {
        $myRoles = $this->getEffectiveRolesForStore($storeId);
        $targetRoles = $targetUser->getEffectiveRolesForStore($storeId);

        foreach ($myRoles as $myRole) {
            foreach ($targetRoles as $targetRole) {
                if ($myRole->isHigherThan($targetRole, $storeId)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasPermissionInStore(string $permission, int $storeId): bool
    {
        return $this->getEffectivePermissionsForStore($storeId)->contains('name', $permission);
    }

    public function userStoreSessions()
    {
        return $this->hasMany(UserStoreSession::class);
    }

    public function getCurrentStoreSession()
    {
        return $this->userStoreSessions()
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * ✅ Pivot assignments as real models (UserRoleStore)
     */
    public function roleTenancies()
    {
        return $this->hasMany(UserRoleStore::class, 'user_id')->with(['role', 'store']);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }
}

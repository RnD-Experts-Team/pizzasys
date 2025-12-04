<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Store;
use App\Models\UserDevice;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasApiTokens,HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    /**
     * Get user with roles and permissions
     */
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
            'roleTenancies.store'
        ]);
    }

    /**
     * Get role permissions (not direct permissions)
     */
    public function getRolePermissions()
    {
        $rolePermissions = collect();
        foreach ($this->roles as $role) {
            $rolePermissions = $rolePermissions->merge($role->permissions);
        }
        return $rolePermissions->unique('id')->values();
    }

    /**
     * Get stores with associated roles
     */
    public function getStoresWithRoles()
    {
        $storeRolesMap = [];
        
        foreach ($this->roleTenancies as $assignment) {
            $storeId = $assignment->store->id;
            
            if (!isset($storeRolesMap[$storeId])) {
                $storeRolesMap[$storeId] = [
                    'store' => [
                        'id' => $assignment->store->id,
                        'name' => $assignment->store->name,
                        'metadata' => $assignment->store->metadata,
                        'is_active' => $assignment->store->is_active
                    ],
                    'roles' => []
                ];
            }
            
            $storeRolesMap[$storeId]['roles'][] = [
                'id' => $assignment->role->id,
                'name' => $assignment->role->name,
                'guard_name' => $assignment->role->guard_name,
                'assignment_metadata' => $assignment->metadata,
                'is_active' => $assignment->is_active,
                'assigned_at' => $assignment->created_at
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

    public function getRolesForStore(string $storeId)
    {
        return $this->storeRoles()
                    ->wherePivot('store_id', $storeId)
                    ->wherePivot('is_active', true)
                    ->get();
    }

    public function getStoresForRole(Role $role)
    {
        return $this->stores()
                    ->wherePivot('role_id', $role->id)
                    ->wherePivot('is_active', true)
                    ->get();
    }

    public function getEffectiveRolesForStore(string $storeId)
    {
        $directRoles = $this->getRolesForStore($storeId);
        $allRoles = collect($directRoles->all());
        
        foreach ($directRoles as $role) {
            $inheritedRoles = $role->getAllLowerRolesForStore($storeId);
            $allRoles = $allRoles->merge($inheritedRoles);
        }
        
        return $allRoles->unique('id');
    }

    public function getEffectivePermissionsForStore(string $storeId)
    {
        $allRoles = $this->getEffectiveRolesForStore($storeId);
        $allPermissions = collect();
        
        foreach ($allRoles as $role) {
            $allPermissions = $allPermissions->merge($role->permissions);
        }
        
        return $allPermissions->unique('id');
    }

    public function canActOnUserInStore(User $targetUser, string $storeId): bool
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

    public function hasPermissionInStore(string $permission, string $storeId): bool
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

     public function roleTenancies()
    {
        return $this->belongsToMany(Role::class, 'user_role_store')
            ->withPivot('store_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function devices()
{
    return $this->hasMany(UserDevice::class);
}
}

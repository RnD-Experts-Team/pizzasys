<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

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

    public function roleTenancies()
    {
        return $this->hasMany(UserRoleStore::class, 'user_id')->with(['role', 'store']);
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
}

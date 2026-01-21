<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    /**
     * stores.id is now a normal auto-increment PK.
     */
    protected $fillable = [
        'store_id',
        'name',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Many users <-> stores via user_role_store
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role_store')
            ->withPivot('role_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    /**
     * Many roles <-> stores via user_role_store
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role_store')
            ->withPivot('user_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function usersByRole(Role $role)
    {
        return User::whereHas('storeRoles', function ($q) use ($role) {
            $q->where('role_id', $role->id)
                ->where('store_id', $this->id)
                ->where('is_active', true);
        })->get();
    }

    public function roleHierarchies()
    {
        return $this->hasMany(RoleHierarchy::class);
    }

    public function userStoreSessions()
    {
        return $this->hasMany(UserStoreSession::class);
    }
}

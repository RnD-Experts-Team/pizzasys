<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

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

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role_store')
            ->withPivot('role_id', 'metadata', 'is_active')
            ->withTimestamps();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role_store')
            ->withPivot('user_id', 'metadata', 'is_active')
            ->withTimestamps();
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

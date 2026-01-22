<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleHierarchy extends Model
{
    protected $table = 'role_hierarchy';

    protected $fillable = [
        'higher_role_id',
        'lower_role_id',
        'store_id',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function higherRole()
    {
        return $this->belongsTo(Role::class, 'higher_role_id');
    }

    public function lowerRole()
    {
        return $this->belongsTo(Role::class, 'lower_role_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}

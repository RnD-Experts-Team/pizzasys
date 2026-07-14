<?php

namespace App\Models;

use App\Models\Concerns\HasStoreScopedRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable
{
    use HasApiTokens, HasRoles, HasStoreScopedRoles;

    /**
     * Primary key is the hiring-system employee id (supplied by events).
     */
    public $incrementing = false;
    protected $keyType = 'int';

    /**
     * Share the same Spatie roles/permissions pool as users.
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'id',
        'first_name',
        'middle_name',
        'last_name',
        'store_id',
        'active',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected function roleStorePivotTable(): string
    {
        return 'employee_role_store';
    }

    public function roleTenancies()
    {
        return $this->hasMany(EmployeeRoleStore::class, 'employee_id')->with(['role', 'store']);
    }

    public function devices()
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ], fn ($part) => $part !== null && $part !== '')));
    }
}

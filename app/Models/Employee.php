<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Employee extends Authenticatable
{
    use HasApiTokens, HasRoles;

    /**
     * Primary key is the hiring-system employee id (supplied by events).
     */
    public $incrementing = false;
    protected $keyType = 'int';

    /**
     * Share the same Spatie roles/permissions pool as users.
     * Employees only ever hold GLOBAL roles — store scoping is by membership,
     * not by store-roles (see the employee_stores table).
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'id',
        'first_name',
        'middle_name',
        'last_name',
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

    /**
     * Hiring-sourced store memberships (one row per store).
     */
    public function stores()
    {
        return $this->hasMany(EmployeeStore::class, 'employee_id');
    }

    public function devices()
    {
        return $this->hasMany(EmployeeDevice::class);
    }

    /**
     * Store numbers this employee is an ACTIVE member of.
     *
     * @return array<int, string>
     */
    public function activeStoreNumbers(): array
    {
        return $this->stores()
            ->where('active', true)
            ->pluck('store_number')
            ->map(fn ($v) => (string) $v)
            ->all();
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

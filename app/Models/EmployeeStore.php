<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStore extends Model
{
    protected $table = 'employee_stores';

    protected $fillable = [
        'employee_id',
        'store_number',
        'status',
        'active',
        'effective_date',
    ];

    protected $casts = [
        'active' => 'boolean',
        'effective_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStoreSession extends Model
{
    protected $fillable = [
        'user_id', 'store_id', 'session_token', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}

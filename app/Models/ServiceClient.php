<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceClient extends Model
{
    protected $fillable = [
        'name', 'token_hash', 'is_active', 'expires_at', 'notes', 'last_used_at', 'use_count',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
        'use_count'    => 'integer',
    ];
     public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public static function generateToken(): array
    {
        $plain = base64_encode(random_bytes(48));
        $hash = hash('sha256', $plain);
        
        return [
            'plain' => $plain,
            'hash' => $hash
        ];
    }
}

<?php

namespace App\Services\AuthEvents;

use App\Models\AuthOutboxEvent;

class AuthOutboxService
{
    public function record(string $subject, array $payload): AuthOutboxEvent
    {
        return AuthOutboxEvent::create([
            'subject' => $subject,
            'type' => $subject,
            'payload' => $payload,
        ]);
    }
}

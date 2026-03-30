<?php

namespace App\Services\AuthEvents;

use App\Models\AuthOutboxEvent;

class AuthOutboxService
{
    public function record(string $subject, array $payload): AuthOutboxEvent
    {

        $subject = $this->applyEnvironmentPrefix($subject);
        return AuthOutboxEvent::create([
            'subject' => $subject,
            'type' => $subject,
            'payload' => $payload,
        ]);
    }
    private function applyEnvironmentPrefix(string $subject): string
    {
        if (!config('nats.dev_mode')) {
            return $subject;
        }

        // Only transform auth + notifications domains
        if (str_starts_with($subject, 'auth.v1.')) {
            return str_replace('auth.v1.', 'auth.testing.v1.', $subject);
        }

        if (str_starts_with($subject, 'notifications.v1.')) {
            return str_replace('notifications.v1.', 'notifications.testing.v1.', $subject);
        }

        return $subject;
    }
}

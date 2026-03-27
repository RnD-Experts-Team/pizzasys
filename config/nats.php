<?php

return [
    'host' => env('NATS_HOST', '127.0.0.1'),
    'port' => (int) env('NATS_PORT', 4222),
    'user' => env('NATS_USER'),
    'pass' => env('NATS_PASS'),
    'token' => env('NATS_TOKEN'),

    /**
     * Publish targets
     * These are the streams this service is allowed to publish to.
     */
    'publishers' => [
        [
            'name' => env('NATS_AUTH_STREAM', 'AUTH_EVENTS'),
            'subjects' => ['auth.v1.>'],
        ],
        [
            'name' => env('NATS_NOTIFICATIONS_STREAM', 'NOTIFICATIONS_EVENTS'),
            'subjects' => ['notifications.v1.>'],
        ],
    ],

    /**
     * Streams consumed by THIS service.
     * In auth service this may be empty if auth only publishes.
     */
    'streams' => [
        // Usually auth service does not consume in this setup.
    ],

    'pull' => [
        'batch' => (int) env('NATS_PULL_BATCH', 25),
        'timeout_ms' => (int) env('NATS_PULL_TIMEOUT_MS', 2000),
        'sleep_ms' => (int) env('NATS_PULL_SLEEP_MS', 250),
    ],
];
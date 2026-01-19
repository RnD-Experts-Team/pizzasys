<?php

return [
    'host' => env('NATS_HOST', '127.0.0.1'),
    'port' => (int) env('NATS_PORT', 4222),
    'user' => env('NATS_USER'),
    'pass' => env('NATS_PASS'),
    'token' => env('NATS_TOKEN'),

    'jetstream' => [
        'enabled' => true,
        'stream' => env('NATS_AUTH_STREAM', 'AUTH_EVENTS'),
        'subjects' => ['auth.v1.>'],
    ],
];

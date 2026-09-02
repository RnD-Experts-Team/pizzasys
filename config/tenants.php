<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed tenant domains
    |--------------------------------------------------------------------------
    |
    | Root domains the mobile app is allowed to trust when it receives a
    | `?domain=` deep link (https://auth.lcportal.cloud/open?domain=...).
    | Onboarding a new client is a one-line addition here + a deploy — no
    | app release needed. Same pattern as SANCTUM_STATEFUL_DOMAINS below.
    |
    */
    'domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANT_ALLOWED_DOMAINS', 'lcportal.cloud'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Universal Link / App Link verification
    |--------------------------------------------------------------------------
    |
    | Served from /.well-known/apple-app-site-association and
    | /.well-known/assetlinks.json (see routes/web.php). Neither value is
    | derivable from this codebase — they come from whoever holds the Apple
    | Developer account (Team ID) and the production Android signing
    | certificate (SHA-256 fingerprint; not available until real release
    | signing replaces the current debug-signed release build).
    |
    */
    'ios_app_id' => env('APPLE_TEAM_ID', '').'.com.pneunited.pnestaffapp',
    'android_package' => 'com.pneunited.pnestaffapp',
    'android_sha256_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANDROID_SIGNING_CERT_SHA256', ''))
    ))),
];

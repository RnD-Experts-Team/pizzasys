<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Mobile deep-link support (pnestaffapp multi-tenant "open in app" flow).
// See pnestaffapp's core/tenant/ module and CLAUDE.md for the full design.

Route::get('/open', function (Request $request) {
    return view('open', ['domain' => (string) $request->query('domain', '')]);
})->name('open');

Route::get('/known-domains.json', function () {
    return response()
        ->json(['domains' => config('tenants.domains')])
        ->header('Cache-Control', 'public, max-age=3600');
});

Route::get('/.well-known/apple-app-site-association', function () {
    return response()->json([
        'applinks' => [
            'apps' => [],
            'details' => [[
                'appID' => config('tenants.ios_app_id'),
                'paths' => ['/open', '/open?*'],
            ]],
        ],
    ]);
});

Route::get('/.well-known/assetlinks.json', function () {
    return response()->json([[
        'relation' => ['delegate_permission/common.handle_all_urls'],
        'target' => [
            'namespace' => 'android_app',
            'package_name' => config('tenants.android_package'),
            'sha256_cert_fingerprints' => config('tenants.android_sha256_fingerprints'),
        ],
    ]]);
});

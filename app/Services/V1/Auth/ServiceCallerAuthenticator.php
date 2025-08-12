<?php

namespace App\Services\V1\Auth;

use App\Models\ServiceClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCallerAuthenticator
{
    public function validate(Request $request): ServiceClient
    {
        $authHeader = $request->header('Authorization');
        $serviceName = (string) $request->input('service', '');

        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ') || $serviceName === '') {
            abort(401, 'Unauthorized caller.');
        }

        $providedToken = trim(Str::after($authHeader, 'Bearer '));
        $client = ServiceClient::where('name', $serviceName)->first();
        if (!$client) abort(401, 'Unknown service.');

        $providedHash = hash('sha256', $providedToken);
        if (!hash_equals($client->token_hash, $providedHash)) abort(401, 'Invalid service token.');
        if (!$client->is_active) abort(401, 'Service is inactive.');
        if ($client->expires_at && now()->greaterThan($client->expires_at)) abort(401, 'Service token expired.');

        $client->forceFill(['last_used_at' => now(), 'use_count' => ($client->use_count ?? 0) + 1])->save();

        return $client;
    }
}

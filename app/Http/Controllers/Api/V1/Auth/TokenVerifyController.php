<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\V1\Auth\AuthorizationResolver;
use App\Services\V1\Auth\ServiceCallerAuthenticator;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenVerifyController extends Controller
{
    public function handle(
        Request $request,
        ServiceCallerAuthenticator $callerAuth,
        AuthorizationResolver $authz
    ) {
        // Verify calling service
        $callerAuth->validate($request);

        // Input
        $service   = (string) $request->input('service', '');
        $token     = (string) $request->input('token', '');
        $method    = strtoupper((string) $request->input('method', 'GET'));
        $path      = (string) $request->input('path', '/');
        $routeName = $request->input('route_name');

        if ($token === '' || !str_contains($token, '|')) {
            return response()->json(['active' => false]);
        }

        // Sanctum: token lookup + hash check + expiry
        [$tokenId, $tokenPart] = explode('|', $token, 2);
        $accessToken = PersonalAccessToken::find($tokenId);
        if (!$accessToken) return response()->json(['active' => false]);

        $expectedHash = hash('sha256', $tokenPart);
        if (!hash_equals($accessToken->token, $expectedHash)) return response()->json(['active' => false]);

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            return response()->json(['active' => false]);
        }

        $user = $accessToken->tokenable;
        if (!$user) return response()->json(['active' => false]);

        // Roles/permissions/abilities
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [];
        $perms = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->values()->all() : [];
        $abilities = (array) ($accessToken->abilities ?? []);
        $scopeStr  = implode(' ', $abilities);

        $exp = $accessToken->expires_at ? $accessToken->expires_at->timestamp : null;
        $iat = $accessToken->created_at ? $accessToken->created_at->timestamp : null;

        // Authorization via DB rules (friendly DSL)
        [$authorized, $requiredPermissions, $grantedBy] =
            $authz->check($service, $method, $path, $routeName, $roles, $perms, $abilities);

        // Response
        return response()->json([
            'active'      => true,
            'scope'       => $scopeStr,
            'token_type'  => 'access_token',
            'exp'         => $exp,
            'iat'         => $iat,
            'sub'         => (string) $user->getKey(),
            'aud'         => $service,
            'iss'         => config('app.url'),
            'jti'         => (string) $accessToken->id,
            'user'        => [
                'id'    => $user->getKey(),
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'roles'       => $roles,
            'permissions' => $perms,
            'ext' => [
                'authorized'           => $authorized,
                'required_permissions' => $requiredPermissions,
                'granted_by'           => $grantedBy,
                'context'              => [
                    'service'    => $service,
                    'method'     => $method,
                    'path'       => $path,
                    'route_name' => $routeName,
                ],
            ],
        ]);
    }
}

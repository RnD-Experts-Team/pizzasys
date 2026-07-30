<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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
        // 1) Verify calling service
        $callerAuth->validate($request);

        // 2) Input
        $service = (string) $request->input('service', '');
        $token = (string) $request->input('token', '');
        $method = strtoupper((string) $request->input('method', 'GET'));
        $path = (string) $request->input('path', '/');
        $routeName = $request->input('route_name');

        // Strip /api prefix so rules are written without it
        if (str_starts_with($path, '/api/')) {
            $path = substr($path, 4); // "/api/stores/1" → "/stores/1"
        } elseif ($path === '/api') {
            $path = '/';
        }

        $storeContext = (array) ($request->input('store_context', []));
        $storeContext = [
            'path' => (array) ($storeContext['path'] ?? []),
            'query' => (array) ($storeContext['query'] ?? []),
            'body' => (array) ($storeContext['body'] ?? []),
            'header' => (array) ($storeContext['header'] ?? []),
        ];

        if ($token === '' || !str_contains($token, '|')) {
            return response()->json(['active' => false]);
        }

        // 3) Sanctum token lookup + hash check + expiry
        [$tokenId, $tokenPart] = explode('|', $token, 2);
        $accessToken = PersonalAccessToken::find($tokenId);
        if (!$accessToken)
            return response()->json(['active' => false]);

        $expectedHash = hash('sha256', $tokenPart);
        if (!hash_equals((string) $accessToken->token, $expectedHash))
            return response()->json(['active' => false]);

        if ($accessToken->expires_at && now()->greaterThan($accessToken->expires_at)) {
            return response()->json(['active' => false]);
        }

        $user = $accessToken->tokenable;
        if (!$user)
            return response()->json(['active' => false]);

        $subjectType = $user instanceof Employee ? 'employee' : 'user';

        // Inactive employees are treated like a revoked credential.
        if ($subjectType === 'employee' && !$user->active) {
            return response()->json(['active' => false]);
        }

        // 4) Roles/permissions/abilities
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [];
        $perms = method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->values()->all() : [];
        $abilities = (array) ($accessToken->abilities ?? []);
        $scopeStr = implode(' ', $abilities);

        $exp = $accessToken->expires_at ? $accessToken->expires_at->timestamp : null;
        $iat = $accessToken->created_at ? $accessToken->created_at->timestamp : null;

        // 5) Authorization via DB rules + store context
        [$authorized, $requiredPermissions, $grantedBy, $meta] =
            $authz->check($service, $method, $path, $routeName, $roles, $perms, $abilities, $storeContext, $user);

        return response()->json([
            'active' => true,
            'scope' => $scopeStr,
            'token_type' => 'access_token',
            'exp' => $exp,
            'iat' => $iat,
            'sub' => (string) $user->getKey(),
            'aud' => $service,
            'iss' => config('app.url'),
            'jti' => (string) $accessToken->id,
            'subject_type' => $subjectType,
            'user' => [
                'id' => $user->getKey(),
                'name' => $subjectType === 'employee' ? $user->full_name : $user->name,
                'email' => $user->email ?? null,
            ],
            'roles' => $roles,
            'permissions' => $perms,
            'ext' => [
                'authorized' => $authorized,
                'required_permissions' => $requiredPermissions,
                'granted_by' => $grantedBy,
                'subject_type' => $subjectType,
                'store' => $meta, // includes store_ids + store_mode (+ per_store if scoped)
                'context' => [
                    'service' => $service,
                    'method' => $method,
                    'path' => $path,
                    'route_name' => $routeName,
                ],
            ],
        ]);
    }
}

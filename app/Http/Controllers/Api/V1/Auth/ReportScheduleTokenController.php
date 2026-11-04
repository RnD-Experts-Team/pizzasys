<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ReportScheduleTokenRequest;
use App\Services\V1\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportScheduleTokenController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function store(ReportScheduleTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $name = 'report-schedule:' . $request->integer('schedule_id');

        $overview = $this->authService->getAuthorizationOverview($user);

        // Permissions the report dataset / builder rules ask for...
        $needed = $overview['auth_rules']
            ->filter(fn($rule) => Str::startsWith((string) $rule->path_dsl, ['/report-datasets/', '/report-builder/']))
            ->flatMap(fn($rule) => array_merge($rule->permissions_any ?? [], $rule->permissions_all ?? []));

        // ...that the user holds today, globally or in any store.
        $held = collect($overview['full_permissions'])->pluck('name')
            ->merge(collect($overview['store_assignments'])->flatMap(
                fn($store) => collect($store['all_permissions_from_store_roles'])->pluck('name')
            ));

        // Never '*': an empty ability list would mean "everything" to the resolver.
        $abilities = $held->intersect($needed)->unique()->values()->all() ?: ['report-builder'];

        $user->tokens()->where('name', $name)->delete();

        $expiresAt = now()->addYear();
        $token = $user->createToken($name, $abilities, $expiresAt);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ], 201);
    }

    public function destroy(Request $request, int $scheduleId): JsonResponse
    {
        $request->user()->tokens()->where('name', 'report-schedule:' . $scheduleId)->delete();

        return response()->json(null, 204);
    }
}

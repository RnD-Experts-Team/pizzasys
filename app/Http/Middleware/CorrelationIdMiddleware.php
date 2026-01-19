<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerKey = 'X-Correlation-Id';

        $incoming = $request->headers->get($headerKey);
        $correlationId = $this->sanitize($incoming) ?: (string) Str::uuid();

        // Attach to request (so services can read it)
        $request->attributes->set('correlation_id', $correlationId);

        /** @var Response $response */
        $response = $next($request);

        // Return it back to client always
        $response->headers->set($headerKey, $correlationId);

        return $response;
    }

    private function sanitize(?string $value): ?string
    {
        if (!$value) return null;

        $value = trim($value);

        // Keep it simple + safe for logs/headers
        if (strlen($value) < 8 || strlen($value) > 128) return null;
        if (!preg_match('/^[a-zA-Z0-9\-\_\.]+$/', $value)) return null;

        return $value;
    }
}

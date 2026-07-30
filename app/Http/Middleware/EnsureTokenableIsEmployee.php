<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenableIsEmployee
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() instanceof Employee) {
            abort(403, 'This endpoint is only accessible to employees.');
        }

        return $next($request);
    }
}

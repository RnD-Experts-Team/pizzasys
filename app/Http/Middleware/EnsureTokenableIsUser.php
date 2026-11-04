<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenableIsUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() instanceof User) {
            abort(403, 'This endpoint is only accessible to users.');
        }

        // Restricted tokens (report schedules) may only call the other services,
        // never pizzasys's own user routes.
        if (!$request->user()->tokenCan('*')) {
            abort(403, 'This token cannot call this endpoint.');
        }

        return $next($request);
    }
}

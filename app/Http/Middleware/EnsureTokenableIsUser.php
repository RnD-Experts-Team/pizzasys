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

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            auth()->guard('web')->user()?->hasRole('admin'),
            403,
            'Diese Seite ist nur für Administratoren zugänglich.'
        );

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageHomepage
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            auth()->guard('web')->user()?->hasAnyRole(['admin', 'homepage']),
            403,
            'Diese Seite ist nur für Homepage-Verantwortliche und Administratoren zugänglich.'
        );

        return $next($request);
    }
}

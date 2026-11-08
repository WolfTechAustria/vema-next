<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageInventory
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            auth()->guard('web')->user()?->hasAnyRole(['admin', 'zeugwart']),
            403,
            'Diese Seite ist nur für Zeugwarte und Administratoren zugänglich.'
        );

        return $next($request);
    }
}

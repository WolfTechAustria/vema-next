<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageInvoices
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            auth()->guard('web')->user()?->hasAnyRole(['admin', 'kassier']),
            403,
            'Diese Seite ist nur für Kassier und Administratoren zugänglich.'
        );

        return $next($request);
    }
}

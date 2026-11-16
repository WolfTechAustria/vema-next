<?php

namespace App\Http\Middleware;

use App\Services\EventAttendance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageAttendance
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            EventAttendance::canManage(),
            403,
            'Diese Seite ist nur für die Anwesenheitsverwaltung und Administratoren zugänglich.'
        );

        return $next($request);
    }
}

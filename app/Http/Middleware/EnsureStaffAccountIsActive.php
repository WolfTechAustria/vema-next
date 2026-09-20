<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt einen deaktivierten Benutzer sofort aus, statt erst beim nächsten
 * Login-Versuch — "enabled" wurde bisher nur in LoginController geprüft,
 * eine bereits laufende Sitzung eines gesperrten Benutzers blieb also
 * gültig, bis sie von selbst abläuft.
 */
class EnsureStaffAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && !$user->enabled) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Dieser Benutzer wurde gesperrt.']);
        }

        return $next($request);
    }
}

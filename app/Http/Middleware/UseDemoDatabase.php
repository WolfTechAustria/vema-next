<?php

namespace App\Http\Middleware;

use App\Services\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schaltet Sitzungen im Testmodus auf die Test-Datenbank um. Läuft direkt
 * nach StartSession (siehe bootstrap/app.php) — also bevor der Auth-Benutzer
 * geladen und Route-Parameter an Models gebunden werden.
 */
class UseDemoDatabase
{
    public function __construct(private DemoMode $demoMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $this->demoMode->isActive()) {
            return $next($request);
        }

        if (! $this->demoMode->isAvailable()) {
            $this->demoMode->leave();

            return redirect()
                ->route('dashboard')
                ->with('error', 'Der Testmodus wurde deaktiviert. Du arbeitest wieder mit den Live-Daten.');
        }

        $this->demoMode->apply();

        $guard = Auth::guard('web');
        $user = $guard->user();

        if (! $user) {
            $hasStaffLogin = $request->session()->has($guard->getName());

            $this->demoMode->leave();

            // Kein Staff-Login (mehr) in dieser Sitzung: Flag war nur verwaist.
            if (! $hasStaffLogin) {
                return $next($request);
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Dein Benutzer existiert in der Test-Datenbank nicht. Bitte die Testdaten in den Einstellungen auf den Live-Stand zurücksetzen.');
        }

        $this->demoMode->redirectMailTo($user->email);

        return $next($request);
    }
}

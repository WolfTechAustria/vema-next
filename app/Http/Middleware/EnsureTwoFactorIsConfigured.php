<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\SupportLoginController;
use App\Services\TwoFactorAuthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verlangt der Verein die Zwei-Faktor-Anmeldung, müssen Funktionäre sie
 * einrichten, bevor sie VEMA weiter nutzen. Der Plattform-Support ist
 * davon ausgenommen.
 */
class EnsureTwoFactorIsConfigured
{
    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (
            $user
            && ! $request->routeIs('logout')
            && ! $request->session()->has(SupportLoginController::SESSION_KEY)
            && $this->twoFactor->isRequired()
            && ! $this->twoFactor->isEnabled($user)
        ) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}

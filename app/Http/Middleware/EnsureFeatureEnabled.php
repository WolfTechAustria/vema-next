<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Services\PlanEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt Routen, deren Funktion im Paket des Vereins nicht enthalten ist
 * (Route-Middleware "feature:duty_plan"). Im Modus "single" wirkungslos.
 */
class EnsureFeatureEnabled
{
    public function __construct(private PlanEntitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $feature = Feature::from($feature);

        if ($this->entitlements->allows($feature)) {
            return $next($request);
        }

        $message = $this->entitlements->featureMessage($feature);

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            abort(403, $message);
        }

        // Mitgliederportal: Mitglieder haben kein Dashboard.
        if ($request->routeIs('member.*')) {
            return response()->view('tenancy.feature-unavailable', ['message' => $message], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }
}

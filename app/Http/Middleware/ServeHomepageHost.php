<?php

namespace App\Http\Middleware;

use App\Services\HomepageHosts;
use App\Services\HomepageRedirects;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Globale Middleware (vor dem Routing): Anfragen an die eigene Domain einer
 * Vereinshomepage werden intern auf die Homepage-Routen unter /site
 * umgeleitet. Dort sind damit ausschließlich öffentliche Homepage-Seiten
 * erreichbar — keine Anmeldung, kein Verwaltungsbereich. Erzeugte Links
 * verlieren den Präfix wieder, damit die Adressen sauber bleiben.
 *
 * Für Suchmaschinen: „www.“ wird auf die Domain ohne umgeleitet, und alte
 * Adressen, die es nicht mehr gibt, leiten auf ihr Gegenstück weiter.
 */
class ServeHomepageHost
{
    public function __construct(
        private HomepageHosts $hosts,
        private HomepageRedirects $redirects,
        private TenantManager $tenantManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hosts->matches($request->getHost())) {
            return $next($request);
        }

        $preferredHost = $this->hosts->preferredHost($request->getHost());

        if ($preferredHost !== Str::lower($request->getHost())) {
            return new RedirectResponse($request->getScheme().'://'.$preferredHost.$request->getRequestUri(), 301);
        }

        $this->hosts->markActive();

        $prefix = '/'.HomepageHosts::PATH_PREFIX;

        URL::formatPathUsing(function (string $path) use ($prefix): string {
            if ($path === $prefix) {
                return '/';
            }

            return Str::startsWith($path, $prefix.'/') ? Str::after($path, $prefix) : $path;
        });

        $path = rtrim($request->getPathInfo(), '/');
        $query = $request->getQueryString();

        $rewritten = $request->duplicate(server: array_merge($request->server->all(), [
            'REQUEST_URI' => $prefix.$path.($query !== null ? '?'.$query : ''),
        ]));

        $response = $next($rewritten);

        // Im Modus "multi" erst, wenn der Verein erkannt wurde (Datenbank).
        $hasClubDatabase = ! $this->tenantManager->isMultiTenant() || $this->tenantManager->current() !== null;

        if ($response->getStatusCode() === 404 && $request->isMethod('GET') && $hasClubDatabase) {
            return $this->redirectFor($path) ?? $response;
        }

        return $response;
    }

    private function redirectFor(string $path): ?RedirectResponse
    {
        $target = $this->redirects->resolve($path);

        if ($target === null) {
            $this->redirects->recordNotFound($path);

            return null;
        }

        return new RedirectResponse(str_starts_with($target, '/') ? url($target) : $target, 301);
    }
}

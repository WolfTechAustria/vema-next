<?php

namespace App\Http\Middleware;

use App\Services\HomepageHosts;
use Closure;
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
 */
class ServeHomepageHost
{
    public function __construct(private HomepageHosts $hosts) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hosts->matches($request->getHost())) {
            return $next($request);
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

        return $next($rewritten);
    }
}

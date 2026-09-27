<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandantenfähigkeit (TENANCY_MODE=multi): erkennt den Verein ausschließlich
 * am Host und schaltet auf dessen Datenbank um. Läuft direkt nach
 * StartSession und vor UseDemoDatabase, Auth und Route-Model-Binding.
 *
 * - Plattform-Domain: nur Plattform-Routen (routes/central.php)
 * - Subdomain/eigene Domain eines Vereins: Vereins-Routen, sofern freigeschaltet
 * - unbekannter Host: 404
 */
class IdentifyTenant
{
    public const SESSION_KEY = 'tenancy.tenant_id';

    /**
     * Routen, die auf der Plattform-Domain erreichbar sind.
     *
     * @var array<int, string>
     */
    private const CENTRAL_ROUTE_PATTERNS = ['central.*', 'livewire.*', 'default-livewire.*'];

    public function __construct(private TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantManager->isMultiTenant()) {
            return $next($request);
        }

        $host = $request->getHost();

        if ($this->tenantManager->isCentralHost($host)) {
            abort_unless($request->routeIs(...self::CENTRAL_ROUTE_PATTERNS), 404);

            return $next($request);
        }

        abort_if($request->routeIs('central.*'), 404);

        $tenant = $this->tenantManager->findByHost($host);

        abort_if($tenant === null, 404);

        if (! $tenant->isAccessible()) {
            return response()->view('tenancy.unavailable', ['tenant' => $tenant], 403);
        }

        $this->tenantManager->apply($tenant);

        if ($request->hasSession()) {
            $this->bindSessionToTenant($request, $tenant->getKey());
        }

        return $next($request);
    }

    /**
     * Zusätzlich zum Host-gebundenen Cookie: Eine Sitzung gehört genau einem
     * Verein. Taucht sie bei einem anderen auf, wird sie verworfen.
     */
    private function bindSessionToTenant(Request $request, int $tenantId): void
    {
        $session = $request->session();
        $sessionTenantId = $session->get(self::SESSION_KEY);

        if ($sessionTenantId !== null && (int) $sessionTenantId !== $tenantId) {
            // Bewusst kein Auth::logout(): das würde den Benutzer mit der
            // gespeicherten ID aus der Datenbank DIESES Vereins laden.
            $session->invalidate();
            $session->regenerateToken();
        }

        $session->put(self::SESSION_KEY, $tenantId);
    }
}

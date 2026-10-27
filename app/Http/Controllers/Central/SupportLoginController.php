<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * „Als Verein anmelden“: leitet den Plattform-Admin mit einem signierten
 * Einmal-Link zur Subdomain des Vereins weiter, ohne eigenes Passwort.
 */
class SupportLoginController extends Controller
{
    public const LINK_MINUTES = 2;

    public function __invoke(Tenant $tenant, TenantManager $tenantManager): RedirectResponse
    {
        if ($tenant->provisioned_at === null || ! $tenant->isAccessible()) {
            return back()->with('error', 'Nur für eingerichtete, freigeschaltete Vereine möglich.');
        }

        $admin = auth('platform')->user();

        // Signatur über die Vereins-URL — auf anderen Hosts ungültig.
        $url = $tenantManager->runFor($tenant, fn (Tenant $tenant): string => URL::temporarySignedRoute(
            'support.login',
            now()->addMinutes(self::LINK_MINUTES),
            [
                'tenant' => $tenant->getKey(),
                'admin' => $admin->getKey(),
                'nonce' => Str::random(40),
            ],
        ));

        $tenant->recordEvent('Als Verein angemeldet');

        return redirect()->away($url);
    }
}

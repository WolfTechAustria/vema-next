<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Central\SupportLoginController as CentralSupportLoginController;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Ziel des Links „Als Verein anmelden“ aus dem Plattform-Admin: meldet den
 * Support als Admin des Vereins an. Der Link ist signiert (Host, Ablauf) und
 * nur einmal verwendbar.
 */
class SupportLoginController extends Controller
{
    public const SESSION_KEY = 'support_login';

    public function __invoke(Request $request, TenantManager $tenantManager): RedirectResponse
    {
        $tenant = $tenantManager->current();

        abort_if($tenant === null || (int) $request->query('tenant') !== $tenant->getKey(), 404);

        $nonceKey = 'support-login:'.$request->query('nonce');

        abort_unless(
            Cache::add($nonceKey, true, now()->addMinutes(CentralSupportLoginController::LINK_MINUTES + 1)),
            403,
            'Dieser Anmeldelink wurde bereits verwendet.'
        );

        $platformAdmin = PlatformAdmin::query()->findOrFail($request->query('admin'));
        $user = User::clubAdmin($tenant->contact_email);

        abort_if($user === null, 404, 'Im Verein gibt es keinen aktiven Admin-Zugang.');

        // Eine vorhandene Anmeldung (Mitglied oder Vorstand) wird ersetzt.
        Auth::guard('member')->logout();
        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $platformAdmin->name);

        ActivityLogger::log(
            'user.support_login',
            'Plattform-Support ('.$platformAdmin->name.') hat sich als "'.$user->username.'" angemeldet.',
            'User',
            $user->id
        );

        return redirect()->route('dashboard');
    }
}

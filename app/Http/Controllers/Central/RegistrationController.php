<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\TenantRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/**
 * Bestätigungslink aus der Registrierungsmail. GET zeigt nur eine Seite mit
 * Button — Link-Scanner von Mailprogrammen öffnen Links automatisch und
 * sollen den Verein nicht einrichten. Eingerichtet wird erst per POST.
 */
class RegistrationController extends Controller
{
    public function showVerification(string $token, TenantRegistration $registration): View|Response
    {
        $tenant = $registration->findPendingByToken($token);

        if (! $tenant) {
            return response()->view('central.verification-invalid', status: 404);
        }

        return view('central.verify', [
            'tenant' => $tenant,
            'token' => $token,
        ]);
    }

    public function verify(string $token, TenantRegistration $registration): RedirectResponse|Response
    {
        $tenant = $registration->findPendingByToken($token);

        if (! $tenant) {
            return response()->view('central.verification-invalid', status: 404);
        }

        try {
            $passwordSetupUrl = $registration->verifyAndProvision($tenant);
        } catch (Throwable) {
            return response()->view('central.provisioning-failed', ['tenant' => $tenant], 500);
        }

        return redirect()->away($passwordSetupUrl);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\DemoMode;
use Illuminate\Http\RedirectResponse;

class DemoModeController extends Controller
{
    public function enter(DemoMode $demoMode): RedirectResponse
    {
        if (! $demoMode->enter()) {
            return back()->with('error', $demoMode->isAvailable()
                ? 'Die Test-Datenbank ist nicht bereit oder dein Benutzer fehlt dort. Bitte die Testdaten in den Einstellungen auf den Live-Stand zurücksetzen.'
                : 'Der Testmodus ist derzeit nicht verfügbar.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Testmodus gestartet – Änderungen betreffen nicht die Live-Daten.');
    }

    public function leave(DemoMode $demoMode): RedirectResponse
    {
        $demoMode->leave();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Testmodus beendet – du arbeitest wieder mit den Live-Daten.');
    }
}

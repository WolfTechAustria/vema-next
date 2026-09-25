<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Testmodus für Staff-Benutzer: Die Sitzung arbeitet gegen eine Kopie der
 * Live-Datenbank. Umgeschaltet wird pro Request über die Default-Connection
 * (siehe UseDemoDatabase) — kein Model legt seine Connection selbst fest.
 */
class DemoMode
{
    public const SESSION_KEY = 'demo_mode';

    private ?string $liveStorageRoot = null;

    public function isActive(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public function liveConnection(): string
    {
        return config('demo.live_connection');
    }

    public function demoConnection(): string
    {
        return config('demo.connection');
    }

    /**
     * Die Testmodus-Einstellungen liegen immer in der Live-DB, auch wenn die
     * aktuelle Sitzung gerade im Testmodus ist.
     */
    public function settings(): Setting
    {
        return Setting::on($this->liveConnection())->firstOrCreate([], [
            'name' => 'VEMA',
        ]);
    }

    public function isAvailable(): bool
    {
        return (bool) $this->settings()->demo_enabled;
    }

    /**
     * Wechselt die Sitzung des angemeldeten Staff-Benutzers in den Testmodus.
     * Gibt false zurück, wenn der Testmodus deaktiviert ist oder der Benutzer
     * in der Test-DB (noch) nicht existiert.
     */
    public function enter(): bool
    {
        $user = Auth::guard('web')->user();

        if (! $user || ! $this->isAvailable() || ! $this->userExistsInDemo($user)) {
            return false;
        }

        ActivityLogger::log('demo.entered', 'Testmodus gestartet.');

        session()->put(self::SESSION_KEY, true);
        $this->renewSession();

        return true;
    }

    public function leave(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->renewSession();

        $this->restore();

        if (Auth::guard('web')->check()) {
            ActivityLogger::log('demo.left', 'Testmodus beendet.');
        }
    }

    /**
     * Leitet für den laufenden Request Datenbank, Dateien und Mails in den
     * Testmodus um. Muss laufen, bevor der Auth-Benutzer geladen wird.
     */
    public function apply(): void
    {
        $live = $this->liveConnection();

        // Session, Cache und Queue bleiben auf der Live-DB, sonst gingen sie
        // beim Umschalten der Default-Connection verloren.
        foreach (['session.connection', 'cache.stores.database.connection', 'cache.stores.database.lock_connection', 'queue.connections.database.connection'] as $key) {
            if (config($key) === null) {
                config([$key => $live]);
            }
        }

        DB::setDefaultConnection($this->demoConnection());

        $this->liveStorageRoot ??= config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => config('demo.storage_root')]);
        Storage::forgetDisk('local');
    }

    /**
     * Mails gehen im Testmodus ausschließlich an den Tester selbst.
     */
    public function redirectMailTo(?string $address): void
    {
        config(['mail.to' => $address ? ['address' => $address, 'name' => null] : null]);

        Mail::forgetMailers();
    }

    public function restore(): void
    {
        DB::setDefaultConnection($this->liveConnection());

        if ($this->liveStorageRoot !== null) {
            config(['filesystems.disks.local.root' => $this->liveStorageRoot]);
            Storage::forgetDisk('local');
        }

        $this->redirectMailTo(null);
    }

    /**
     * Neuer CSRF-Token: Offene Tabs aus dem jeweils anderen Modus laufen so
     * in "Seite abgelaufen", statt Daten in die falsche Datenbank zu schreiben.
     */
    private function renewSession(): void
    {
        session()->regenerate();
        session()->regenerateToken();
    }

    /**
     * False auch dann, wenn die Test-DB nicht erreichbar oder noch leer ist.
     */
    public function userExistsInDemo(User $user): bool
    {
        try {
            return DB::connection($this->demoConnection())
                ->table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->exists();
        } catch (QueryException) {
            return false;
        }
    }
}

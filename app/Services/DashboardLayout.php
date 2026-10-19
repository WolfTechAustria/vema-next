<?php

namespace App\Services;

use App\Enums\DashboardModule;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserDashboard;

/**
 * Welche Dashboard-Module ein Benutzer in welcher Reihenfolge sieht:
 * eigene Anordnung → Vereinsstandard → eingebaute Standard-Anordnung,
 * jeweils beschränkt auf Module, die der Benutzer öffnen darf.
 */
class DashboardLayout
{
    /**
     * @return list<DashboardModule>
     */
    public function modulesFor(User $user): array
    {
        $keys = UserDashboard::where('userID', $user->id)->value('modules')
            ?? $this->clubDefaultKeys()
            ?? $this->keys(DashboardModule::defaultOrder());

        return $this->availableModules($user, $keys);
    }

    public function hasPersonalLayout(User $user): bool
    {
        return UserDashboard::where('userID', $user->id)->exists();
    }

    /**
     * @param  list<string>  $keys
     */
    public function save(User $user, array $keys): void
    {
        UserDashboard::updateOrCreate(
            ['userID' => $user->id],
            ['modules' => $this->keys($this->availableModules($user, $keys))]
        );
    }

    public function reset(User $user): void
    {
        UserDashboard::where('userID', $user->id)->delete();
    }

    /**
     * Unbekannte Schlüssel werden verworfen. Rollenabhängige Module bleiben
     * im Standard erhalten und erscheinen nur bei Benutzern mit Zugriff.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function saveClubDefault(array $keys): array
    {
        $modules = $this->keys($this->knownModules($keys));

        Setting::current()->update(['dashboard_modules' => $modules]);

        return $modules;
    }

    /**
     * @return list<string>|null
     */
    public function clubDefaultKeys(): ?array
    {
        return Setting::current()->dashboard_modules;
    }

    /**
     * @param  list<string>  $keys
     * @return list<DashboardModule>
     */
    private function availableModules(User $user, array $keys): array
    {
        return array_values(array_filter(
            $this->knownModules($keys),
            fn (DashboardModule $module): bool => $module->isAvailableFor($user)
        ));
    }

    /**
     * @param  list<string>  $keys
     * @return list<DashboardModule>
     */
    private function knownModules(array $keys): array
    {
        $modules = [];

        foreach ($keys as $key) {
            $module = is_string($key) ? DashboardModule::tryFrom($key) : null;

            if ($module) {
                $modules[$module->value] = $module;
            }
        }

        return array_values($modules);
    }

    /**
     * @param  list<DashboardModule>  $modules
     * @return list<string>
     */
    private function keys(array $modules): array
    {
        return array_map(fn (DashboardModule $module): string => $module->value, $modules);
    }
}

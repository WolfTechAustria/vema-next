<?php

namespace App\Models\Concerns;

use App\Enums\PushCategory;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * Web-Push für Funktionäre und Mitglieder-Accounts: Abos je Gerät und die
 * gewählten Kategorien (Spalte "push_categories", leer = alle).
 */
trait ReceivesPushNotifications
{
    use HasPushSubscriptions;

    public function initializeReceivesPushNotifications(): void
    {
        $this->mergeCasts(['push_categories' => 'array']);
    }

    public function wantsPush(PushCategory $category): bool
    {
        return in_array($category->value, $this->enabledPushCategories(), true);
    }

    /**
     * @return list<string>
     */
    public function enabledPushCategories(): array
    {
        return $this->push_categories ?? array_map(fn (PushCategory $category) => $category->value, PushCategory::cases());
    }

    public function togglePushCategory(PushCategory $category): void
    {
        $enabled = $this->enabledPushCategories();

        $enabled = in_array($category->value, $enabled, true)
            ? array_values(array_diff($enabled, [$category->value]))
            : [...$enabled, $category->value];

        $this->forceFill(['push_categories' => $enabled])->save();
    }
}

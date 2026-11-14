<?php

namespace App\Livewire;

use App\Enums\PushCategory;
use App\Models\MemberAccount;
use App\Models\User;
use App\Notifications\PushMessage;
use App\Services\ActivityLogger;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Push-Benachrichtigungen einrichten: Gerät anmelden/abmelden und
 * Kategorien wählen – für Funktionäre und für Mitglieder.
 */
class PushSettings extends Component
{
    #[Locked]
    public string $guard = 'web';

    public function mount(): void
    {
        $this->guard = request()->routeIs('member.*') ? 'member' : 'web';
    }

    /**
     * Abo des Browsers speichern (PushSubscription.toJSON()).
     *
     * @param  array{endpoint?: string, keys?: array{p256dh?: string, auth?: string}, contentEncoding?: string}  $subscription
     */
    public function subscribe(array $subscription): void
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');

        abort_unless(str_starts_with($endpoint, 'https://') && strlen($endpoint) <= 1024, 422);

        $account = $this->account();

        $account->updatePushSubscription(
            $endpoint,
            $subscription['keys']['p256dh'] ?? null,
            $subscription['keys']['auth'] ?? null,
            $subscription['contentEncoding'] ?? 'aes128gcm',
        );

        ActivityLogger::log(
            $this->guard === 'member' ? 'portal.push_device_added' : 'push.device_added',
            'Push-Benachrichtigungen wurden auf einem Gerät aktiviert.',
            class_basename($account),
            $account->getKey()
        );
    }

    public function unsubscribe(string $endpoint): void
    {
        $this->account()->deletePushSubscription($endpoint);
    }

    public function toggleCategory(string $category): void
    {
        $category = PushCategory::tryFrom($category);

        abort_unless($category && in_array($category, $this->categories(), true), 404);

        $this->account()->togglePushCategory($category);
    }

    public function sendTest(PushNotifier $notifier): void
    {
        abort_unless($notifier->isConfigured(), 404);

        $account = $this->account();

        Notification::sendNow($account, new PushMessage(
            $this->categories()[0],
            'Test von VEMA',
            'Push-Benachrichtigungen funktionieren auf diesem Gerät.',
            $this->guard === 'member' ? route('member.notifications') : route('notifications'),
            'vema-test',
        ));

        session()->flash('success', 'Testnachricht wurde an deine Geräte gesendet.');
    }

    /**
     * @return list<PushCategory>
     */
    private function categories(): array
    {
        return $this->guard === 'member' ? PushCategory::forMembers() : PushCategory::forStaff();
    }

    private function account(): User|MemberAccount
    {
        $account = Auth::guard($this->guard)->user();

        abort_unless($account instanceof User || $account instanceof MemberAccount, 403);

        return $account;
    }

    public function render(PushNotifier $notifier)
    {
        $account = $this->account();

        return view('livewire.push-settings', [
            'configured' => $notifier->isConfigured(),
            'publicKey' => $notifier->publicKey(),
            'categories' => $this->categories(),
            'enabled' => $account->enabledPushCategories(),
            'endpoints' => $account->pushSubscriptions()->pluck('endpoint')->all(),
            'deviceCount' => $account->pushSubscriptions()->count(),
        ])->layout('layouts.app', [
            'title' => 'Benachrichtigungen | VEMA',
            'heading' => 'Benachrichtigungen',
        ]);
    }
}

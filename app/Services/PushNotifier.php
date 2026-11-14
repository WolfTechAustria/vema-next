<?php

namespace App\Services;

use App\Enums\Feature;
use App\Enums\PushCategory;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberEmail;
use App\Models\User;
use App\Notifications\PushMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Verschickt Push-Benachrichtigungen an Mitglieder (über deren
 * Mitglieder-Accounts) und Funktionäre. Fehler beim Versand werden nur
 * protokolliert – eine Push-Nachricht darf nie eine Aktion scheitern lassen.
 */
class PushNotifier
{
    public function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('webpush.vapid.public_key');
    }

    /**
     * @param  Member|iterable<Member>  $members
     */
    public function toMembers(Member|iterable $members, PushCategory $category, string $title, string $body, string $url, ?string $tag = null): void
    {
        if (! $this->isConfigured() || ! app(PlanEntitlements::class)->allows(Feature::MemberPortal)) {
            return;
        }

        $memberIDs = collect($members instanceof Member ? [$members] : $members)
            ->map(fn (Member $member) => $member->memberID)
            ->unique()
            ->values();

        if ($memberIDs->isEmpty()) {
            return;
        }

        $this->send($this->accountsFor($memberIDs), new PushMessage($category, $title, $body, $url, $tag));
    }

    /**
     * An alle aktiven Admins.
     */
    public function toAdmins(PushCategory $category, string $title, string $body, string $url, ?string $tag = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $admins = User::role('admin')->where('enabled', true)->whereHas('pushSubscriptions')->get();

        $this->send($admins, new PushMessage($category, $title, $body, $url, $tag));
    }

    /**
     * Mitglieder-Accounts der Mitglieder (Login per E-Mail-Adresse, eine
     * Familie kann sich einen Account teilen) – nur solche mit Geräten.
     *
     * @param  Collection<int, int>  $memberIDs
     * @return Collection<int, MemberAccount>
     */
    private function accountsFor(Collection $memberIDs): Collection
    {
        $emails = MemberEmail::query()
            ->whereIn('memberID', $memberIDs)
            ->whereHas('member', fn ($query) => $query->where('active', true))
            ->pluck('email')
            ->unique();

        if ($emails->isEmpty()) {
            return collect();
        }

        return MemberAccount::query()
            ->whereIn('email', $emails)
            ->whereHas('pushSubscriptions')
            ->get();
    }

    /**
     * @param  iterable<object>  $notifiables
     */
    private function send(iterable $notifiables, PushMessage $message): void
    {
        $notifiables = collect($notifiables);

        if ($notifiables->isEmpty()) {
            return;
        }

        try {
            Notification::sendNow($notifiables, $message);
        } catch (Throwable $exception) {
            Log::warning('Push-Benachrichtigung konnte nicht versendet werden.', [
                'title' => $message->title,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Livewire\MemberPortal;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\MemberEventSettings;
use App\Services\ActiveMemberResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyEvents extends Component
{
    public string $icalUrl = '';

    public function mount(ActiveMemberResolver $resolver): void
    {
        $settings = MemberEventSettings::forMember($resolver->resolve());

        $this->icalUrl = route('events.ical', $settings->getOrCreateIcalToken());
    }

    public function respond(int $eventID, string $status): void
    {
        $member = app(ActiveMemberResolver::class)->resolve();
        $responseStatus = EventResponseStatus::from($status);

        $event = ClubEvent::query()
            ->upcoming()
            ->where('rsvp_enabled', true)
            ->findOrFail($eventID);

        ClubEventResponse::query()->updateOrCreate(
            ['eventID' => $event->eventID, 'memberID' => $member->memberID],
            ['status' => $responseStatus],
        );

        session()->flash(
            'success',
            $responseStatus === EventResponseStatus::Attending
                ? 'Du hast für „'.$event->title.'“ zugesagt.'
                : 'Du hast für „'.$event->title.'“ abgesagt.'
        );
    }

    public function regenerateIcalLink(): void
    {
        $settings = MemberEventSettings::forMember(app(ActiveMemberResolver::class)->resolve());

        $this->icalUrl = route('events.ical', $settings->regenerateIcalToken());

        session()->flash(
            'success',
            'Der Kalender-Link wurde erneuert. Der alte Link funktioniert nicht mehr.'
        );
    }

    public function render(): View
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $events = ClubEvent::query()
            ->upcoming()
            ->with(['responses' => fn ($query) => $query->where('memberID', $member->memberID)])
            ->orderBy('starts_at')
            ->get();

        return view('livewire.member-portal.my-events', [
            'events' => $events,
            'member' => $member,
        ])->layout('layouts.app', [
            'title' => 'Termine | VEMA',
            'heading' => 'Termine',
        ]);
    }
}

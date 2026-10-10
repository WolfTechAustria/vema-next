<?php

namespace App\Livewire\MemberPortal;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\ClubEventSource;
use App\Models\MemberEventSettings;
use App\Services\ActiveMemberResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyEvents extends Component
{
    public string $icalUrl = '';

    /**
     * Anzahl der angezeigten anstehenden Termine („Mehr anzeigen“ erhöht sie).
     */
    public int $visibleUpcomingCount = self::UPCOMING_PAGE_SIZE;

    private const UPCOMING_PAGE_SIZE = 5;

    public function mount(ActiveMemberResolver $resolver): void
    {
        $settings = MemberEventSettings::forMember($resolver->resolve());

        $this->icalUrl = route('events.ical', $settings->getOrCreateIcalToken());
    }

    public function showMoreEvents(): void
    {
        $this->visibleUpcomingCount += self::UPCOMING_PAGE_SIZE;
    }

    public function respond(int $eventID, string $status): void
    {
        $member = app(ActiveMemberResolver::class)->resolve();
        $responseStatus = EventResponseStatus::from($status);

        $event = ClubEvent::query()
            ->upcoming()
            ->visibleToMembers()
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

    /**
     * Termine eines externen Kalenders ins persönliche iCal-Abo übernehmen
     * oder wieder entfernen.
     */
    public function toggleSourceSubscription(int $sourceID): void
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $source = ClubEventSource::query()
            ->where('active', true)
            ->findOrFail($sourceID);

        $result = $member->subscribedEventSources()->toggle([$source->sourceID]);

        session()->flash(
            'success',
            $result['attached'] !== []
                ? '„'.$source->name.'“ wird in dein Kalender-Abo übernommen.'
                : '„'.$source->name.'“ wurde aus deinem Kalender-Abo entfernt.'
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

        $eventsQuery = ClubEvent::query()
            ->upcoming()
            ->visibleToMembers();

        $totalEvents = $eventsQuery->count();

        $events = $eventsQuery
            ->with(['source', 'responses' => fn ($query) => $query->where('memberID', $member->memberID)])
            ->orderBy('starts_at')
            ->limit($this->visibleUpcomingCount)
            ->get();

        return view('livewire.member-portal.my-events', [
            'events' => $events,
            'hiddenEventsCount' => $totalEvents - $events->count(),
            'member' => $member,
            'sources' => ClubEventSource::query()->where('active', true)->orderBy('name')->get(),
            'subscribedSourceIDs' => $member->subscribedEventSources()->pluck('tb_event_sources.sourceID')->all(),
        ])->layout('layouts.app', [
            'title' => 'Termine | VEMA',
            'heading' => 'Termine',
        ]);
    }
}

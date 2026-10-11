<?php

namespace App\Livewire\MemberPortal;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\ClubEventResponse;
use App\Models\ClubEventSource;
use App\Models\MemberDutySettings;
use App\Services\ActiveMemberResolver;
use App\Services\ActivityLogger;
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
        $settings = MemberDutySettings::forMember($resolver->resolve());

        $this->icalUrl = route('member-calendar.ical', $settings->getOrCreateIcalToken());
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
            ->visibleTo($member)
            ->where('rsvp_enabled', true)
            ->findOrFail($eventID);

        $previousStatus = ClubEventResponse::query()
            ->where('eventID', $event->eventID)
            ->where('memberID', $member->memberID)
            ->first()
            ?->status;

        ClubEventResponse::query()->updateOrCreate(
            ['eventID' => $event->eventID, 'memberID' => $member->memberID],
            ['status' => $responseStatus],
        );

        if ($previousStatus !== $responseStatus) {
            ActivityLogger::log(
                'portal.event_responded',
                'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat für Termin '.$event->activityName()
                    .($responseStatus === EventResponseStatus::Attending ? ' zugesagt.' : ' abgesagt.'),
                'ClubEvent',
                $event->eventID,
                ActivityLogger::changes(
                    ['status' => $previousStatus],
                    ['status' => $responseStatus],
                    ['status' => 'Rückmeldung']
                )
            );
        }

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
        $isSubscribed = $result['attached'] !== [];

        ActivityLogger::log(
            'portal.calendar_source_toggled',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat den Kalender „'.$source->name.'“ '
                .($isSubscribed ? 'in sein Kalender-Abo übernommen.' : 'aus seinem Kalender-Abo entfernt.'),
            'ClubEventSource',
            $source->sourceID,
            ActivityLogger::changes(
                ['subscribed' => ! $isSubscribed],
                ['subscribed' => $isSubscribed],
                ['subscribed' => 'Im Kalender-Abo']
            )
        );

        session()->flash(
            'success',
            $result['attached'] !== []
                ? '„'.$source->name.'“ wird in dein Kalender-Abo übernommen.'
                : '„'.$source->name.'“ wurde aus deinem Kalender-Abo entfernt.'
        );
    }

    public function regenerateIcalLink(): void
    {
        $member = app(ActiveMemberResolver::class)->resolve();
        $settings = MemberDutySettings::forMember($member);
        $previousToken = $settings->ical_token;

        $this->icalUrl = route('member-calendar.ical', $settings->regenerateIcalToken());

        ActivityLogger::log(
            'portal.calendar_link_renewed',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat seinen Kalender-Link erneuert.',
            'Member',
            $member->memberID,
            ActivityLogger::changes(
                ['ical_token' => $previousToken],
                ['ical_token' => $settings->ical_token],
                ['ical_token' => 'Kalender-Link'],
                ['ical_token']
            )
        );

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
            ->visibleTo($member);

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

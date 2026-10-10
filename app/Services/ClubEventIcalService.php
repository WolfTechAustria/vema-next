<?php

namespace App\Services;

use App\Enums\EventResponseStatus;
use App\Models\ClubEvent;
use App\Models\Member;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class ClubEventIcalService
{
    /**
     * Trägt die Vereinstermine in den (gemeinsamen) Kalender des Mitglieds
     * ein — abgesagte Termine werden ausgeblendet, externe Kalender nur nach
     * Opt-in des Mitglieds übernommen.
     */
    public function addToCalendar(Calendar $calendar, Member $member): void
    {
        $events = ClubEvent::query()
            ->inCalendarOf($member)
            ->where('starts_at', '>=', now()->subMonths(1)->startOfDay())
            ->with(['responses' => fn ($query) => $query->where('memberID', $member->memberID)])
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (ClubEvent $event) => $event->responseFor($member)?->status === EventResponseStatus::Declined);

        foreach ($events as $event) {
            $icalEvent = Event::create($event->title)
                ->uniqueIdentifier('club-event-'.$event->eventID);

            if ($event->description) {
                $icalEvent->description($event->description);
            }

            if ($event->location) {
                $icalEvent->address($event->location);
            }

            if ($event->all_day) {
                $icalEvent->startsAt($event->starts_at, withTime: false)
                    ->endsAt(($event->ends_at ?? $event->starts_at)->copy()->addDay(), withTime: false);
            } else {
                $icalEvent->startsAt($event->starts_at)
                    ->endsAt($event->ends_at ?? $event->starts_at->copy()->addHour());
            }

            $calendar->event($icalEvent);
        }
    }
}

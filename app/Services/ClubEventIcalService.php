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
     * Vereinskalender für ein Mitglied — abgesagte Termine werden ausgeblendet.
     */
    public function buildForMember(Member $member): Calendar
    {
        $events = ClubEvent::query()
            ->where('starts_at', '>=', now()->subMonths(1)->startOfDay())
            ->with(['responses' => fn ($query) => $query->where('memberID', $member->memberID)])
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (ClubEvent $event) => $event->responseFor($member)?->status === EventResponseStatus::Declined);

        $calendar = Calendar::create('Vereinstermine')
            ->refreshInterval(60)
            ->withoutTimezone();

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

        return $calendar;
    }
}

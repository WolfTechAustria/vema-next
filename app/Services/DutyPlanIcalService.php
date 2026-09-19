<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class DutyPlanIcalService
{
    public function buildForMember(Member $member): Calendar
    {
        $assignments = $member->dutyAssignments()
            ->with('event')
            ->whereHas('event', fn ($query) => $query->where(
                'duty_date',
                '>=',
                now()->subMonths(1)->toDateString()
            ))
            ->get()
            ->filter(fn ($assignment) => $assignment->event !== null);

        $calendar = Calendar::create('Dienste ' . $member->full_name)
            ->refreshInterval(60)
            ->withoutTimezone();

        foreach ($assignments as $assignment) {
            $event = $assignment->event;

            $icalEvent = Event::create($event->duty_name ?? 'Dienst')
                ->uniqueIdentifier('duty-assignment-' . $assignment->assignmentID)
                ->description(
                    'Dienst "' . ($event->duty_name ?? 'Dienst')
                    . '" für ' . $member->full_name
                );

            if ($event->start_time) {
                $start = Carbon::parse(
                    $event->duty_date->toDateString() . ' ' . $event->start_time
                );

                $end = $event->end_time
                    ? Carbon::parse($event->duty_date->toDateString() . ' ' . $event->end_time)
                    : $start->copy()->addHour();

                $icalEvent->startsAt($start)->endsAt($end);
            } else {
                // Kein Beginn hinterlegt (z. B. Training/Saisonabend ohne Uhrzeit):
                // wie bisher ganztägig anzeigen.
                $icalEvent->startsAt($event->duty_date, withTime: false)->fullDay();
            }

            $calendar->event($icalEvent);
        }

        return $calendar;
    }
}

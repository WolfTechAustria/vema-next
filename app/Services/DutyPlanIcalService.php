<?php

namespace App\Services;

use App\Models\Member;
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

            $calendar->event(
                Event::create($event->duty_name ?? 'Dienst')
                    ->uniqueIdentifier('duty-assignment-' . $assignment->assignmentID)
                    ->startsAt($event->duty_date, withTime: false)   // NEU: Datum hier setzen
                    ->fullDay()                                       // NEU: ohne Parameter
                    ->description(
                        'Dienst "' . ($event->duty_name ?? 'Dienst')
                        . '" für ' . $member->full_name
                    )
            );
        }

        return $calendar;
    }
}

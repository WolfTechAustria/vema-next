<?php

namespace App\Services;

use App\Models\DutyPlanAssignment;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class DutyPlanIcalService
{
    /**
     * Trägt die Dienste des Mitglieds in den (gemeinsamen) Kalender ein.
     */
    public function addToCalendar(Calendar $calendar, Member $member): void
    {
        foreach ($this->assignments($member) as $assignment) {
            $name = $assignment->event->duty_name ?? 'Dienst';

            $calendar->event(
                $this->eventFor($assignment, $name)
                    ->uniqueIdentifier('duty-assignment-'.$assignment->assignmentID)
                    ->description('Dienst "'.$name.'" für '.$member->full_name)
            );
        }
    }

    /**
     * Trägt die Dienste eines anderen Mitglieds ein, das sie freigegeben hat
     * – deutlich als fremde Dienste gekennzeichnet.
     */
    public function addSharedToCalendar(Calendar $calendar, Member $owner): void
    {
        foreach ($this->assignments($owner) as $assignment) {
            $name = $assignment->event->duty_name ?? 'Dienst';

            $calendar->event(
                $this->eventFor($assignment, 'Dienst von '.$owner->full_name.': '.$name)
                    ->uniqueIdentifier('shared-duty-assignment-'.$assignment->assignmentID)
                    ->description('Freigegebener Dienst "'.$name.'" von '.$owner->full_name.' – nicht dein eigener Dienst.')
            );
        }
    }

    /**
     * @return Collection<int, DutyPlanAssignment>
     */
    private function assignments(Member $member): Collection
    {
        return $member->dutyAssignments()
            ->with('event')
            ->whereHas('event', fn ($query) => $query->where(
                'duty_date',
                '>=',
                now()->subMonths(1)->toDateString()
            ))
            ->get()
            ->filter(fn ($assignment) => $assignment->event !== null);
    }

    private function eventFor(DutyPlanAssignment $assignment, string $title): Event
    {
        $event = $assignment->event;
        $icalEvent = Event::create($title);

        if ($event->start_time) {
            $start = Carbon::parse(
                $event->duty_date->toDateString().' '.$event->start_time
            );

            $end = $event->end_time
                ? Carbon::parse($event->duty_date->toDateString().' '.$event->end_time)
                : $start->copy()->addHour();

            return $icalEvent->startsAt($start)->endsAt($end);
        }

        // Kein Beginn hinterlegt (z. B. Training/Saisonabend ohne Uhrzeit):
        // wie bisher ganztägig anzeigen.
        return $icalEvent->startsAt($event->duty_date, withTime: false)->fullDay();
    }
}

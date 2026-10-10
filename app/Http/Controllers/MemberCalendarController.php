<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\MemberDutySettings;
use App\Services\ClubEventIcalService;
use App\Services\DutyPlanIcalService;
use App\Services\PlanEntitlements;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;

/**
 * Persönliches Kalender-Abo eines Mitglieds: ein Link für Dienste und
 * Vereinstermine (jeweils nur, wenn das Paket die Funktion enthält).
 */
class MemberCalendarController extends Controller
{
    public function show(
        string $token,
        PlanEntitlements $entitlements,
        DutyPlanIcalService $dutyPlanIcal,
        ClubEventIcalService $clubEventIcal,
    ): Response {
        $includesDuties = $entitlements->allows(Feature::DutyPlan);
        $includesEvents = $entitlements->allows(Feature::Events);

        // Kalender-Abo: kein Browser, der eine Paket-Meldung anzeigen könnte.
        abort_unless($includesDuties || $includesEvents, 404);

        $settings = MemberDutySettings::query()
            ->where('ical_token', $token)
            ->firstOrFail();

        $member = $settings->member;

        abort_if(! $member || ! $member->active, 404);

        $calendar = Calendar::create('Dienste & Termine '.$member->full_name)
            ->refreshInterval(60)
            ->withoutTimezone();

        if ($includesDuties) {
            $dutyPlanIcal->addToCalendar($calendar, $member);
        }

        if ($includesEvents) {
            $clubEventIcal->addToCalendar($calendar, $member);
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="kalender.ics"',
        ]);
    }
}

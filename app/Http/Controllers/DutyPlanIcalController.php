<?php

namespace App\Http\Controllers;

use App\Models\MemberDutySettings;
use App\Services\DutyPlanIcalService;

class DutyPlanIcalController extends Controller
{
    public function show(string $token, DutyPlanIcalService $service)
    {
        $settings = MemberDutySettings::query()
            ->where('ical_token', $token)
            ->firstOrFail();

        $member = $settings->member;

        abort_if(!$member || !$member->active, 404);

        $calendar = $service->buildForMember($member);

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="dienste.ics"',
        ]);
    }
}

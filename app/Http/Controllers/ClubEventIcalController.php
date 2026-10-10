<?php

namespace App\Http\Controllers;

use App\Models\MemberEventSettings;
use App\Services\ClubEventIcalService;
use Illuminate\Http\Response;

class ClubEventIcalController extends Controller
{
    public function show(string $token, ClubEventIcalService $service): Response
    {
        $settings = MemberEventSettings::query()
            ->where('ical_token', $token)
            ->firstOrFail();

        $member = $settings->member;

        abort_if(! $member || ! $member->active, 404);

        return response($service->buildForMember($member)->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="vereinstermine.ics"',
        ]);
    }
}

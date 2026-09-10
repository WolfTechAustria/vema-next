<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlanVolunteer;
use App\Models\Member;
use Livewire\Component;

class Volunteers extends Component
{
    public string $search = '';

    public function toggleVolunteer(int $memberId): void
    {
        $volunteer = DutyPlanVolunteer::query()
            ->where('memberID', $memberId)
            ->first();

        if (!$volunteer) {
            DutyPlanVolunteer::create([
                'memberID' => $memberId,
                'active' => true,
                'weekday_mask' => 127,
            ]);

            return;
        }

        $volunteer->update([
            'active' => !$volunteer->active,
        ]);
    }

    public function toggleWeekday(
        int $memberId,
        int $weekday
    ): void {
        if ($weekday < 1 || $weekday > 7) {
            return;
        }

        $volunteer = DutyPlanVolunteer::query()
            ->where('memberID', $memberId)
            ->first();

        if (!$volunteer || !$volunteer->active) {
            return;
        }

        $bit = 1 << ($weekday - 1);

        $volunteer->weekday_mask =
            ($volunteer->weekday_mask & $bit) !== 0
                ? $volunteer->weekday_mask & ~$bit
                : $volunteer->weekday_mask | $bit;

        $volunteer->save();
    }

    public function weekdayName(int $weekday): string
    {
        return match ($weekday) {
            1 => 'Mo',
            2 => 'Di',
            3 => 'Mi',
            4 => 'Do',
            5 => 'Fr',
            6 => 'Sa',
            7 => 'So',
        };
    }

    public function render()
    {
        $members = Member::query()
            ->with('dutyVolunteer')
            ->where('active', 1)
            ->when(
                $this->search,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('surname', 'like', '%' . $this->search . '%');
                    });
                }
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view('livewire.duty-plan.volunteers', [
            'members' => $members,
        ])->layout('layouts.app', [
            'title' => 'Helfer | VEMA',
            'heading' => 'Helferverwaltung',
        ]);
    }
}

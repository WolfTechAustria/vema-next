<?php

namespace App\Livewire\Members;

use App\Models\Member;
use Carbon\Carbon;
use Livewire\Component;

class Birthdays extends Component
{
    public int $month;

    public function mount(): void
    {
        $this->month = now()->month;
    }

    public function updatingMonth(): void
    {
        //
    }

    public function render()
    {
        $members = Member::query()
            ->active()
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $this->month)
            ->orderByRaw('DAY(dateOfBirth)')
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(function (Member $member) {
                $age = $member->ageOn(now());

                return [
                    'member' => $member,
                    'day' => $member->dateOfBirth->day,
                    'age' => $age,
                    'isRound' => $member->isRoundBirthday($age),
                    'isHalfRound' => $member->isHalfRoundBirthday($age),
                ];
            });

        return view('livewire.members.birthdays', [
            'members' => $members,
            'monthName' => now()->setMonth($this->month)->translatedFormat('F'),
        ])->layout('layouts.app', [
            'title' => 'Geburtstage | VEMA',
            'heading' => 'Geburtstage',
        ]);
    }
}

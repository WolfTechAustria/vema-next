<?php

namespace App\Livewire\Members;

use App\Models\Member;
use Livewire\Component;

class Show extends Component
{
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->load([
            'city',
            'emails',
            'phones',
        ]);
    }

    public function render()
    {
        return view('livewire.members.show')
            ->layout('layouts.app', [
                'title' => $this->member->full_name . ' | VEMA',
                'heading' => 'Mitglied',
            ]);
    }
}

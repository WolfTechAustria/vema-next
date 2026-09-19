<?php

namespace App\Livewire\Members;

use App\Models\Member;
use Livewire\Component;

class Show extends Component
{

    public bool $showCircularEmailModal = false;

    public ?int $selectedCircularRecipientID = null;

    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->load([
            'city',
            'emails',
            'phones',
            'dutyAssignments.event',   // NEU

            'circularRecipients' => fn ($query) =>
            $query
                ->with(['circular.attachments'])
                ->latest('sent_at'),
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

    public function showCircularEmail(int $recipientID): void
    {
        $this->selectedCircularRecipientID = $recipientID;
        $this->showCircularEmailModal = true;
    }

}

<?php

namespace App\Livewire\Members;

use App\Models\BoardFunction;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Services\ActivityLogger;
use App\Services\BoardFunctionService;
use App\Services\MembershipFeeEntryService;
use App\Services\MembershipPeriodService;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Show extends Component
{

    public bool $showCircularEmailModal = false;

    public ?int $selectedCircularRecipientID = null;

    public Member $member;

    public bool $showResignModal = false;
    public ?string $resignDate = null;
    public ?string $resignNote = null;

    public bool $showReenterModal = false;
    public ?string $reenterDate = null;
    public ?string $reenterNote = null;

    public bool $showAssignFunctionModal = false;
    public ?int $newBoardFunctionID = null;
    public ?string $newBoardFunctionDate = null;

    public ?int $endingAssignmentID = null;
    public ?string $endFunctionDate = null;

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

        $this->loadMembershipData();
    }

    private function loadMembershipData(): void
    {
        $this->member->load([
            'membershipPeriods' => fn ($query) => $query->orderByDesc('date_from'),
            'boardFunctionAssignments.boardFunction',
        ]);
    }

    public function render()
    {
        return view('livewire.members.show', [
            'boardFunctions' => BoardFunction::active()->orderBy('sort_order')->get(),
            'currentFunctions' => app(BoardFunctionService::class)->currentFunctionsFor($this->member),
        ])
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

    public function openResignModal(): void
    {
        $this->resignDate = now()->format('Y-m-d');
        $this->resignNote = null;
        $this->showResignModal = true;
    }

    public function resign(): void
    {
        $this->validate([
            'resignDate' => ['required', 'date'],
            'resignNote' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(MembershipPeriodService::class)->resign($this->member, $this->resignDate, $this->resignNote ?: null);
        } catch (HttpExceptionInterface $e) {
            session()->flash('error', $e->getMessage());
            $this->showResignModal = false;

            return;
        }

        ActivityLogger::log(
            'member.resigned',
            'Mitglied "' . $this->member->full_name . '" (#' . $this->member->memberID . ') wurde per ' . \Carbon\Carbon::parse($this->resignDate)->format('d.m.Y') . ' als ausgetreten erfasst.',
            'Member',
            $this->member->memberID
        );

        $this->member->refresh();
        $this->loadMembershipData();
        $this->showResignModal = false;

        session()->flash('success', 'Austritt wurde erfasst.');
    }

    public function openReenterModal(): void
    {
        $this->reenterDate = now()->format('Y-m-d');
        $this->reenterNote = null;
        $this->showReenterModal = true;
    }

    public function reenter(): void
    {
        $this->validate([
            'reenterDate' => ['required', 'date'],
            'reenterNote' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(MembershipPeriodService::class)->reenter($this->member, $this->reenterDate, $this->reenterNote ?: null);
        } catch (HttpExceptionInterface $e) {
            session()->flash('error', $e->getMessage());
            $this->showReenterModal = false;

            return;
        }

        app(MembershipFeeEntryService::class)->ensureEntriesForMember($this->member);

        ActivityLogger::log(
            'member.reentered',
            'Mitglied "' . $this->member->full_name . '" (#' . $this->member->memberID . ') wurde per ' . \Carbon\Carbon::parse($this->reenterDate)->format('d.m.Y') . ' als wiedereingetreten erfasst.',
            'Member',
            $this->member->memberID
        );

        $this->member->refresh();
        $this->loadMembershipData();
        $this->showReenterModal = false;

        session()->flash('success', 'Wiedereintritt wurde erfasst.');
    }

    public function openAssignFunctionModal(): void
    {
        $this->newBoardFunctionID = null;
        $this->newBoardFunctionDate = now()->format('Y-m-d');
        $this->showAssignFunctionModal = true;
    }

    public function assignFunction(): void
    {
        $this->validate([
            'newBoardFunctionID' => ['required', 'integer', 'exists:tb_board_functions,boardFunctionID'],
            'newBoardFunctionDate' => ['required', 'date'],
        ]);

        try {
            $assignment = app(BoardFunctionService::class)->assign(
                $this->member,
                $this->newBoardFunctionID,
                $this->newBoardFunctionDate
            );
        } catch (HttpExceptionInterface $e) {
            session()->flash('error', $e->getMessage());
            $this->showAssignFunctionModal = false;

            return;
        }

        ActivityLogger::log(
            'member.board_function_assigned',
            'Mitglied "' . $this->member->full_name . '" (#' . $this->member->memberID . ') wurde die Funktion "' . $assignment->boardFunction->name . '" ab ' . \Carbon\Carbon::parse($this->newBoardFunctionDate)->format('d.m.Y') . ' zugewiesen.',
            'Member',
            $this->member->memberID
        );

        $this->loadMembershipData();
        $this->showAssignFunctionModal = false;

        session()->flash('success', 'Funktion wurde zugewiesen.');
    }

    public function openEndFunctionModal(int $assignmentID): void
    {
        $this->endingAssignmentID = $assignmentID;
        $this->endFunctionDate = now()->format('Y-m-d');
    }

    public function endFunction(): void
    {
        $this->validate([
            'endFunctionDate' => ['required', 'date'],
        ]);

        $assignment = MemberBoardFunctionAssignment::with('boardFunction')->find($this->endingAssignmentID);

        if (!$assignment) {
            $this->endingAssignmentID = null;

            return;
        }

        try {
            app(BoardFunctionService::class)->end($assignment, $this->endFunctionDate);
        } catch (HttpExceptionInterface $e) {
            session()->flash('error', $e->getMessage());
            $this->endingAssignmentID = null;

            return;
        }

        ActivityLogger::log(
            'member.board_function_ended',
            'Die Funktion "' . $assignment->boardFunction->name . '" von Mitglied "' . $this->member->full_name . '" (#' . $this->member->memberID . ') wurde per ' . \Carbon\Carbon::parse($this->endFunctionDate)->format('d.m.Y') . ' beendet.',
            'Member',
            $this->member->memberID
        );

        $this->loadMembershipData();
        $this->endingAssignmentID = null;

        session()->flash('success', 'Funktion wurde beendet.');
    }

}

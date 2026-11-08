<?php

namespace App\Livewire\Members;

use App\Enums\Feature;
use App\Models\BoardFunction;
use App\Models\Honor;
use App\Models\InventoryItem;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Services\ActivityLogger;
use App\Services\BoardFunctionService;
use App\Services\InventoryLoans;
use App\Services\MemberFields;
use App\Services\MemberHonors;
use App\Services\MembershipFeeEntryService;
use App\Services\MembershipPeriodService;
use App\Services\PlanEntitlements;
use App\Services\WelcomeMailService;
use Carbon\Carbon;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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

    public bool $showHonorForm = false;

    public ?int $editingHonorID = null;

    public string $honorName = '';

    public ?string $honorDate = null;

    public string $honorNote = '';

    public bool $showInventoryForm = false;

    public ?int $inventoryItemID = null;

    public string $inventoryIssuedOn = '';

    public string $inventoryDueOn = '';

    public function mount(Member $member): void
    {
        $this->member = $member->load([
            'city',
            'emails',
            'phones',
            'dutyAssignments.event',   // NEU

            'circularRecipients' => fn ($query) => $query
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

    public function openInventoryForm(): void
    {
        abort_unless($this->canManageInventory(), 403);

        $this->resetValidation();
        $this->inventoryItemID = null;
        $this->inventoryIssuedOn = now(config('app.club_timezone'))->toDateString();
        $this->inventoryDueOn = '';
        $this->showInventoryForm = true;
    }

    public function issueInventory(InventoryLoans $loans): void
    {
        abort_unless($this->canManageInventory(), 403);

        $this->validate([
            'inventoryItemID' => ['required', 'integer', 'exists:tb_inventory_items,itemID'],
            'inventoryIssuedOn' => ['required', 'date'],
            'inventoryDueOn' => ['nullable', 'date', 'after_or_equal:inventoryIssuedOn'],
        ], [
            'inventoryItemID.required' => 'Bitte ein Material auswählen.',
        ], [
            'inventoryIssuedOn' => 'Ausgegeben am',
            'inventoryDueOn' => 'Rückgabe bis',
        ]);

        $loans->issue(InventoryItem::findOrFail($this->inventoryItemID), $this->member, $this->inventoryIssuedOn, $this->inventoryDueOn, null, 'inventoryItemID');

        $this->showInventoryForm = false;
        session()->flash('success', 'Material wurde ausgegeben.');
    }

    public function returnInventory(int $loanID, InventoryLoans $loans): void
    {
        abort_unless($this->canManageInventory(), 403);

        $loan = $this->member->openInventoryLoans()->whereKey($loanID)->firstOrFail();

        $loans->return($loan, now(config('app.club_timezone'))->toDateString());

        session()->flash('success', 'Rückgabe wurde erfasst.');
    }

    private function canManageInventory(): bool
    {
        return (bool) auth()->guard('web')->user()?->hasAnyRole(['admin', 'zeugwart'])
            && app(PlanEntitlements::class)->allows(Feature::Inventory);
    }

    public function render()
    {
        $canManageInventory = $this->canManageInventory();

        return view('livewire.members.show', [
            'canManageInventory' => $canManageInventory,
            'inventoryLoans' => $canManageInventory ? $this->member->inventoryLoans()->with('item')->get() : collect(),
            'availableInventoryItems' => $canManageInventory && $this->showInventoryForm
                ? InventoryItem::query()->inService()->whereDoesntHave('currentLoan')->orderBy('name')->get()
                : collect(),
            'boardFunctions' => BoardFunction::active()->orderBy('sort_order')->get(),
            'currentFunctions' => app(BoardFunctionService::class)->currentFunctionsFor($this->member),
            'formerFunctions' => app(BoardFunctionService::class)->formerFunctionsFor($this->member),
            'customFieldValues' => app(MemberFields::class)->displayValues($this->member),
            'honors' => $this->member->honors()->with('honor')->get(),
            'honorSuggestions' => Honor::query()->orderBy('name')->pluck('name'),
        ])
            ->layout('layouts.app', [
                'title' => $this->member->full_name.' | VEMA',
                'heading' => 'Mitglied',
            ]);
    }

    public function showCircularEmail(int $recipientID): void
    {
        $this->selectedCircularRecipientID = $recipientID;
        $this->showCircularEmailModal = true;
    }

    public function sendWelcomeMail(WelcomeMailService $welcomeMail): void
    {
        abort_unless($this->member->active, 422);

        try {
            $sent = $welcomeMail->sendToMember($this->member);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', 'Die Willkommens-E-Mail konnte nicht versendet werden.');

            return;
        }

        session()->flash(
            $sent ? 'success' : 'error',
            $sent
                ? 'Willkommens-E-Mail wurde versendet.'
                : 'Für dieses Mitglied ist keine E-Mail-Adresse hinterlegt.'
        );
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
            'Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde per '.Carbon::parse($this->resignDate)->format('d.m.Y').' als ausgetreten erfasst.',
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
            'Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde per '.Carbon::parse($this->reenterDate)->format('d.m.Y').' als wiedereingetreten erfasst.',
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
            'Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde die Funktion "'.$assignment->boardFunction->name.'" ab '.Carbon::parse($this->newBoardFunctionDate)->format('d.m.Y').' zugewiesen.',
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

        if (! $assignment) {
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
            'Die Funktion "'.$assignment->boardFunction->name.'" von Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde per '.Carbon::parse($this->endFunctionDate)->format('d.m.Y').' beendet.',
            'Member',
            $this->member->memberID
        );

        $this->loadMembershipData();
        $this->endingAssignmentID = null;

        session()->flash('success', 'Funktion wurde beendet.');
    }

    public function openHonorForm(?int $memberHonorID = null): void
    {
        $memberHonor = $memberHonorID ? $this->member->honors()->with('honor')->findOrFail($memberHonorID) : null;

        $this->editingHonorID = $memberHonor?->memberHonorID;
        $this->honorName = $memberHonor?->honor->name ?? '';
        $this->honorDate = $memberHonor ? $memberHonor->awarded_on?->format('Y-m-d') : now()->format('Y-m-d');
        $this->honorNote = $memberHonor?->note ?? '';
        $this->showHonorForm = true;

        $this->resetValidation();
    }

    public function saveHonor(MemberHonors $honors): void
    {
        $this->validate([
            'honorName' => ['required', 'string', 'max:150'],
            'honorDate' => ['nullable', 'date'],
            'honorNote' => ['nullable', 'string', 'max:255'],
        ], [
            'honorName.required' => 'Bitte die Ehrung eingeben.',
        ]);

        if ($this->editingHonorID) {
            $honors->update($this->member->honors()->findOrFail($this->editingHonorID), $this->honorName, $this->honorDate, $this->honorNote);
            session()->flash('success', 'Ehrung wurde gespeichert.');
        } else {
            $honors->add($this->member, $this->honorName, $this->honorDate, $this->honorNote);
            session()->flash('success', 'Ehrung wurde eingetragen.');
        }

        $this->reset(['showHonorForm', 'editingHonorID', 'honorName', 'honorDate', 'honorNote']);
    }

    public function removeHonor(int $memberHonorID, MemberHonors $honors): void
    {
        $honors->remove($this->member->honors()->with('honor')->findOrFail($memberHonorID));

        if ($this->editingHonorID === $memberHonorID) {
            $this->reset(['showHonorForm', 'editingHonorID', 'honorName', 'honorDate', 'honorNote']);
        }

        session()->flash('success', 'Ehrung wurde entfernt.');
    }
}

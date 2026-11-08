<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCondition;
use App\Enums\InventoryInspectionResult;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use App\Services\InventoryInspections;
use App\Services\InventoryItems;
use App\Services\InventoryLoans;
use App\Services\InventoryPhotoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Materialstück mit Ausgaben an Mitglieder und Prüfungen.
 */
class Show extends Component
{
    public InventoryItem $item;

    public bool $showLoanForm = false;

    public string $loanMemberSearch = '';

    public ?int $loanMemberID = null;

    public string $loanIssuedOn = '';

    public string $loanDueOn = '';

    public string $loanNote = '';

    public bool $showReturnForm = false;

    public string $returnedOn = '';

    public string $returnCondition = '';

    public bool $showInspectionForm = false;

    public string $inspectedOn = '';

    public string $inspectionResult = 'ok';

    public string $inspectionNote = '';

    public string $inspectionNextOn = '';

    public function mount(InventoryItem $item): void
    {
        $this->item = $item;
    }

    public function openLoanForm(): void
    {
        $this->resetValidation();
        $this->loanMemberSearch = '';
        $this->loanMemberID = null;
        $this->loanIssuedOn = $this->today();
        $this->loanDueOn = '';
        $this->loanNote = '';
        $this->showLoanForm = true;
    }

    public function selectLoanMember(int $memberID): void
    {
        $this->loanMemberID = $memberID;
        $this->loanMemberSearch = '';
    }

    public function issue(InventoryLoans $loans): void
    {
        $this->validate([
            'loanMemberID' => ['required', 'integer', Rule::exists('tb_members', 'memberID')],
            'loanIssuedOn' => ['required', 'date'],
            'loanDueOn' => ['nullable', 'date', 'after_or_equal:loanIssuedOn'],
            'loanNote' => ['nullable', 'string', 'max:255'],
        ], [
            'loanMemberID.required' => 'Bitte ein Mitglied auswählen.',
        ], [
            'loanIssuedOn' => 'Ausgegeben am',
            'loanDueOn' => 'Rückgabe bis',
            'loanNote' => 'Notiz',
        ]);

        $loans->issue($this->item, Member::findOrFail($this->loanMemberID), $this->loanIssuedOn, $this->loanDueOn, $this->loanNote);

        $this->showLoanForm = false;
        session()->flash('success', 'Material wurde ausgegeben.');
    }

    public function openReturnForm(): void
    {
        $this->resetValidation();
        $this->returnedOn = $this->today();
        $this->returnCondition = $this->item->condition->value;
        $this->showReturnForm = true;
    }

    public function returnItem(InventoryLoans $loans): void
    {
        $loan = $this->item->currentLoan;

        abort_if($loan === null, 404);

        $this->validate([
            'returnedOn' => ['required', 'date', 'after_or_equal:'.$loan->issued_on->toDateString()],
            'returnCondition' => ['required', Rule::enum(InventoryCondition::class)],
        ], [], [
            'returnedOn' => 'Zurückgegeben am',
            'returnCondition' => 'Zustand',
        ]);

        $loans->return($loan, $this->returnedOn, $this->returnCondition);

        $this->showReturnForm = false;
        session()->flash('success', 'Rückgabe wurde erfasst.');
    }

    public function openInspectionForm(): void
    {
        $this->resetValidation();
        $this->inspectedOn = $this->today();
        $this->inspectionResult = InventoryInspectionResult::Ok->value;
        $this->inspectionNote = '';
        $this->inspectionNextOn = '';
        $this->showInspectionForm = true;
    }

    public function recordInspection(InventoryInspections $inspections): void
    {
        $this->validate([
            'inspectedOn' => ['required', 'date'],
            'inspectionResult' => ['required', Rule::enum(InventoryInspectionResult::class)],
            'inspectionNote' => ['nullable', 'string', 'max:255'],
            'inspectionNextOn' => ['nullable', 'date', 'after:inspectedOn'],
        ], [], [
            'inspectedOn' => 'Geprüft am',
            'inspectionResult' => 'Ergebnis',
            'inspectionNote' => 'Notiz',
            'inspectionNextOn' => 'Nächste Prüfung',
        ]);

        $inspections->record(
            $this->item,
            $this->inspectedOn,
            InventoryInspectionResult::from($this->inspectionResult),
            $this->inspectionNote,
            $this->inspectionNextOn ?: null
        );

        $this->item->refresh();
        $this->showInspectionForm = false;
        session()->flash('success', 'Prüfung wurde erfasst.');
    }

    public function delete(InventoryItems $items): void
    {
        $items->delete($this->item);

        session()->flash('success', 'Material wurde gelöscht.');

        $this->redirectRoute('inventory.index', navigate: true);
    }

    /**
     * @return Collection<int, Member>
     */
    private function memberMatches(): Collection
    {
        $search = trim($this->loanMemberSearch);

        if ($search === '') {
            return collect();
        }

        return Member::query()
            ->where('active', 1)
            ->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%'))
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    private function today(): string
    {
        return now(config('app.club_timezone'))->toDateString();
    }

    public function render()
    {
        $this->item->load(['category', 'currentLoan.member', 'loans.member', 'inspections.user']);

        return view('livewire.inventory.show', [
            'currentLoan' => $this->item->currentLoan,
            'memberMatches' => $this->memberMatches(),
            'selectedMember' => $this->loanMemberID ? Member::find($this->loanMemberID) : null,
            'conditions' => InventoryCondition::cases(),
            'inspectionResults' => InventoryInspectionResult::cases(),
            'photoUrl' => app(InventoryPhotoService::class)->url($this->item),
            'loanHistory' => $this->item->loans->reject(fn (InventoryLoan $loan) => $loan->isOpen()),
        ])->layout('layouts.app', [
            'title' => $this->item->name.' | VEMA',
            'heading' => 'Material',
        ]);
    }
}

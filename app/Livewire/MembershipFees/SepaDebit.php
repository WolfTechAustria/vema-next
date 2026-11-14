<?php

namespace App\Livewire\MembershipFees;

use App\Models\MembershipFeeYear;
use App\Models\SepaDebitItem;
use App\Models\SepaDebitRun;
use App\Models\Setting;
use App\Services\SepaDebitRuns;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * SEPA-Lastschrift für ein Beitragsjahr: Beiträge auswählen, Datei
 * erstellen, Lauf nach der Gutschrift bestätigen oder verwerfen,
 * Rücklastschriften erfassen.
 */
class SepaDebit extends Component
{
    #[Locked]
    public MembershipFeeYear $year;

    /** @var list<string> */
    public array $selected = [];

    public string $collectionDate = '';

    public bool $notifyMembers = true;

    public function mount(MembershipFeeYear $year, SepaDebitRuns $runs): void
    {
        $this->year = $year;
        // Vorlauf für die Vorabinformation der Mitglieder (Bankarbeitstage).
        $this->collectionDate = today()->addWeekdays(max(1, (int) Setting::current()->sepa_lead_days))->toDateString();
        $this->selected = $runs->candidates($year)->pluck('entryID')->map(fn ($id) => (string) $id)->all();
    }

    public function createRun(SepaDebitRuns $runs): void
    {
        $this->validate(['collectionDate' => ['required', 'date']], [], ['collectionDate' => 'Einzugsdatum']);

        try {
            $run = $runs->create(
                $this->year,
                array_map('intval', $this->selected),
                Carbon::parse($this->collectionDate),
                auth()->user(),
                $this->notifyMembers,
            );
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        $this->selected = [];

        session()->flash('success', 'Lastschrift-Datei mit '.$run->entry_count.' Beiträgen erstellt. Bitte im Online-Banking hochladen und nach der Gutschrift hier bestätigen.');
    }

    public function confirmRun(int $runID, SepaDebitRuns $runs): void
    {
        $notice = $this->handle(fn () => $runs->confirm($this->run($runID), auth()->user()));

        if ($notice !== false) {
            session()->flash($notice ? 'warning' : 'success', $notice ?: 'Einzug bestätigt – die Beiträge sind als bezahlt markiert.');
        }
    }

    public function cancelRun(int $runID, SepaDebitRuns $runs): void
    {
        if ($this->handle(fn () => $runs->cancel($this->run($runID))) !== false) {
            session()->flash('success', 'Der Lauf wurde verworfen, die Beiträge sind wieder offen.');
        }
    }

    public function markReturned(int $itemID, SepaDebitRuns $runs): void
    {
        $item = SepaDebitItem::query()
            ->whereHas('run', fn ($query) => $query->where('yearID', $this->year->yearID))
            ->find($itemID) ?? abort(404);

        $notice = $this->handle(fn () => $runs->markReturned($item));

        if ($notice !== false) {
            session()->flash($notice ? 'warning' : 'success', 'Rücklastschrift erfasst – der Beitrag ist wieder offen.'.($notice ? ' '.$notice : ''));
        }
    }

    public function selectAll(SepaDebitRuns $runs): void
    {
        $this->selected = $runs->candidates($this->year)->pluck('entryID')->map(fn ($id) => (string) $id)->all();
    }

    public function selectNone(): void
    {
        $this->selected = [];
    }

    /**
     * Führt eine Aktion aus; Validierungsfehler werden als Meldung gezeigt.
     */
    private function handle(callable $action): mixed
    {
        try {
            return $action();
        } catch (ValidationException $exception) {
            session()->flash('error', $exception->validator->errors()->first());

            return false;
        }
    }

    private function run(int $runID): SepaDebitRun
    {
        return SepaDebitRun::query()->where('yearID', $this->year->yearID)->find($runID) ?? abort(404);
    }

    public function render(SepaDebitRuns $runs): View
    {
        $candidates = $runs->candidates($this->year);
        $selectedIDs = array_map('intval', $this->selected);

        return view('livewire.membership-fees.sepa-debit', [
            'candidates' => $candidates,
            'withoutMandate' => $runs->withoutMandate($this->year),
            'selectedTotal' => $candidates->whereIn('entryID', $selectedIDs)->sum(fn ($entry) => (float) $entry->amount),
            'missingSetup' => $runs->missingSetup(),
            'earliestDate' => $runs->earliestCollectionDate(),
            'runs' => SepaDebitRun::query()
                ->with(['items.entry.member'])
                ->where('yearID', $this->year->yearID)
                ->latest('runID')
                ->get(),
        ])->layout('layouts.app', [
            'title' => 'SEPA-Lastschrift | VEMA',
            'heading' => 'Mitgliedsbeiträge',
        ]);
    }
}

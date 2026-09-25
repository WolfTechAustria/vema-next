<?php

namespace App\Livewire\CashBook;

use App\Models\CashBookYear;
use App\Services\ActivityLogger;
use App\Services\CashBookYearCloser;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use RuntimeException;

/**
 * Verwaltung der Vereinsjahre des Kassabuchs inkl. Jahresabschluss nach der
 * Jahreshauptversammlung.
 */
class Years extends Component
{
    public bool $showYearDialog = false;

    public ?int $editingYearId = null;

    public string $name = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $openingBalance = '0,00';

    public ?int $closingYearId = null;

    public string $generalMeetingDate = '';

    public string $closingNote = '';

    public function openCreate(): void
    {
        $this->resetYearForm();

        $latest = CashBookYear::orderByDesc('end_date')->first();

        $start = $latest
            ? $latest->end_date->copy()->addDay()
            : today()->startOfMonth();
        $end = $start->copy()->addYear()->subDay();

        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
        $this->name = CashBookYearCloser::defaultName($start, $end);

        if ($latest) {
            $this->openingBalance = number_format((float) ($latest->closing_balance ?? $latest->balance()), 2, ',', '');
        }

        $this->showYearDialog = true;
    }

    public function openEdit(int $yearId): void
    {
        $year = $this->findOpenYear($yearId);

        $this->resetYearForm();

        $this->editingYearId = $year->cashBookYearID;
        $this->name = $year->name;
        $this->startDate = $year->start_date->toDateString();
        $this->endDate = $year->end_date->toDateString();
        $this->openingBalance = number_format((float) $year->opening_balance, 2, ',', '');

        $this->showYearDialog = true;
    }

    public function closeYearDialog(): void
    {
        $this->showYearDialog = false;
        $this->resetYearForm();
    }

    public function saveYear(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after:startDate'],
            'openingBalance' => ['required', 'string', 'max:20'],
        ], [
            'endDate.after' => 'Das Ende muss nach dem Beginn liegen.',
        ]);

        $openingBalance = $this->normalizeDecimal($validated['openingBalance']);

        if ($openingBalance === null) {
            throw ValidationException::withMessages([
                'openingBalance' => 'Bitte einen gültigen Betrag eingeben (z.B. 1250,00).',
            ]);
        }

        $overlapping = CashBookYear::query()
            ->when($this->editingYearId, fn ($query) => $query->whereKeyNot($this->editingYearId))
            ->whereDate('start_date', '<=', $validated['endDate'])
            ->whereDate('end_date', '>=', $validated['startDate'])
            ->first();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'startDate' => 'Der Zeitraum überschneidet sich mit dem Vereinsjahr '.$overlapping->name.'.',
            ]);
        }

        $attributes = [
            'name' => trim($validated['name']),
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'opening_balance' => round($openingBalance, 2),
        ];

        if ($this->editingYearId !== null) {
            $year = $this->findOpenYear($this->editingYearId);

            $entriesOutside = $year->entries()
                ->where(fn ($query) => $query
                    ->whereDate('date', '<', $validated['startDate'])
                    ->orWhereDate('date', '>', $validated['endDate']))
                ->count();

            if ($entriesOutside > 0) {
                throw ValidationException::withMessages([
                    'startDate' => $entriesOutside.' Buchung(en) liegen außerhalb des neuen Zeitraums.',
                ]);
            }

            $year->update($attributes);

            ActivityLogger::log('cash-book.year-update', 'Vereinsjahr '.$year->name.' geändert', 'cash_book_year', $year->cashBookYearID);

            session()->flash('success', 'Vereinsjahr '.$year->name.' gespeichert.');
        } else {
            $year = CashBookYear::create($attributes);

            ActivityLogger::log('cash-book.year-create', 'Vereinsjahr '.$year->name.' angelegt', 'cash_book_year', $year->cashBookYearID);

            session()->flash('success', 'Vereinsjahr '.$year->name.' angelegt.');
        }

        $this->closeYearDialog();
    }

    public function deleteYear(int $yearId): void
    {
        $year = $this->findOpenYear($yearId);

        if ($year->entries()->exists()) {
            throw ValidationException::withMessages([
                'year' => 'Vereinsjahre mit Buchungen können nicht gelöscht werden.',
            ]);
        }

        $year->delete();

        ActivityLogger::log('cash-book.year-delete', 'Vereinsjahr '.$year->name.' gelöscht', 'cash_book_year', $year->cashBookYearID);

        $this->closeYearDialog();

        session()->flash('success', 'Vereinsjahr '.$year->name.' gelöscht.');
    }

    public function openClose(int $yearId): void
    {
        $this->findOpenYear($yearId);

        $this->resetValidation();
        $this->closingYearId = $yearId;
        $this->generalMeetingDate = today()->toDateString();
        $this->closingNote = '';
    }

    public function cancelClose(): void
    {
        $this->closingYearId = null;
        $this->resetValidation();
    }

    public function closeYear(CashBookYearCloser $closer): void
    {
        $year = $this->findOpenYear((int) $this->closingYearId);

        $validated = $this->validate([
            'generalMeetingDate' => ['required', 'date'],
            'closingNote' => ['nullable', 'string', 'max:2000'],
        ], [
            'generalMeetingDate.required' => 'Bitte das Datum der Jahreshauptversammlung angeben.',
        ]);

        try {
            $closed = $closer->close(
                $year,
                Carbon::parse($validated['generalMeetingDate']),
                $validated['closingNote'],
                Auth::guard('web')->user()
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['year' => $exception->getMessage()]);
        }

        $this->closingYearId = null;

        session()->flash(
            'success',
            'Vereinsjahr '.$closed->name.' abgeschlossen. Endbestand '
                .number_format((float) $closed->closing_balance, 2, ',', '.').' € wurde ins Folgejahr übernommen.'
        );
    }

    public function render(): View
    {
        $years = CashBookYear::query()
            ->with('closedByUser')
            ->withCount('entries')
            ->withCount(['entries as entries_without_receipt_count' => fn ($query) => $query->doesntHave('attachments')])
            ->orderByDesc('start_date')
            ->get()
            ->map(function (CashBookYear $year) {
                $year->setAttribute('summary', $year->totals() + ['balance' => $year->balance()]);

                return $year;
            });

        return view('livewire.cash-book.years', [
            'years' => $years,
            'closingYear' => $years->firstWhere('cashBookYearID', $this->closingYearId),
        ])->layout('layouts.app', [
            'title' => 'Vereinsjahre | Kassabuch | VEMA',
            'heading' => 'Kassabuch',
        ]);
    }

    private function findOpenYear(int $yearId): CashBookYear
    {
        $year = CashBookYear::findOrFail($yearId);

        if ($year->isClosed()) {
            throw ValidationException::withMessages([
                'year' => 'Das Vereinsjahr '.$year->name.' ist bereits abgeschlossen.',
            ]);
        }

        return $year;
    }

    private function resetYearForm(): void
    {
        $this->reset(['editingYearId', 'name', 'startDate', 'endDate', 'openingBalance']);
        $this->resetValidation();
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}

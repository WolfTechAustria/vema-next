<?php

namespace App\Livewire\CashBook;

use App\Enums\CashBookEntryType;
use App\Models\CashBookAttachment;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Kassabuch: Einnahmen und Ausgaben eines Vereinsjahres erfassen, inklusive
 * Belegen (Foto/Scan/PDF). Abgeschlossene Jahre sind nur noch lesbar.
 */
class Index extends Component
{
    use WithFileUploads;

    #[Url(as: 'jahr')]
    public ?int $yearId = null;

    public string $search = '';

    public string $typeFilter = 'all';

    public string $categoryFilter = '';

    public bool $showEntryDialog = false;

    public ?int $editingEntryId = null;

    public string $type = 'expense';

    public string $date = '';

    public string $amount = '';

    public string $description = '';

    public string $category = '';

    public string $note = '';

    /**
     * Zuletzt gewählte Dateien — werden sofort in $files übernommen, damit
     * am Handy mehrere Fotos nacheinander aufgenommen werden können.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $newFiles = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public function mount(): void
    {
        if ($this->yearId === null || ! CashBookYear::whereKey($this->yearId)->exists()) {
            $this->yearId = CashBookYear::current()?->cashBookYearID;
        }
    }

    public function updatedNewFiles(): void
    {
        $this->validate([
            'newFiles.*' => $this->fileRules(),
        ]);

        foreach ($this->newFiles as $file) {
            $this->files[] = $file;
        }

        $this->newFiles = [];
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);

        $this->files = array_values($this->files);
    }

    public function openCreate(string $type = 'expense'): void
    {
        $year = $this->openYearOrFail();

        $this->resetEntryForm();

        $this->type = (CashBookEntryType::tryFrom($type) ?? CashBookEntryType::Expense)->value;
        $this->date = $year->containsDate(today())
            ? today()->toDateString()
            : $year->end_date->toDateString();

        $this->showEntryDialog = true;
    }

    public function openEdit(int $entryId): void
    {
        $entry = $this->findEntry($entryId);

        $this->resetEntryForm();

        $this->editingEntryId = $entry->cashBookEntryID;
        $this->type = $entry->type->value;
        $this->date = $entry->date->toDateString();
        $this->amount = number_format((float) $entry->amount, 2, ',', '');
        $this->description = $entry->description;
        $this->category = (string) $entry->category;
        $this->note = (string) $entry->note;

        $this->showEntryDialog = true;
    }

    public function closeEntryDialog(): void
    {
        $this->showEntryDialog = false;
        $this->resetEntryForm();
    }

    public function saveEntry(): void
    {
        $year = $this->openYearOrFail();

        $validated = $this->validate([
            'type' => ['required', Rule::enum(CashBookEntryType::class)],
            'date' => [
                'required',
                'date',
                'after_or_equal:'.$year->start_date->toDateString(),
                'before_or_equal:'.$year->end_date->toDateString(),
            ],
            'amount' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'files.*' => $this->fileRules(),
        ], [
            'date.after_or_equal' => 'Das Datum muss im Vereinsjahr '.$year->name.' liegen ('
                .$year->start_date->format('d.m.Y').' – '.$year->end_date->format('d.m.Y').').',
            'date.before_or_equal' => 'Das Datum muss im Vereinsjahr '.$year->name.' liegen ('
                .$year->start_date->format('d.m.Y').' – '.$year->end_date->format('d.m.Y').').',
        ]);

        $amount = $this->normalizeDecimal($validated['amount']);

        if ($amount === null || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Bitte einen gültigen Betrag größer 0 eingeben (z.B. 12,50).',
            ]);
        }

        $attributes = [
            'type' => $validated['type'],
            'date' => $validated['date'],
            'amount' => round($amount, 2),
            'description' => trim($validated['description']),
            'category' => trim((string) $validated['category']) ?: null,
            'note' => trim((string) $validated['note']) ?: null,
        ];

        $entry = DB::transaction(function () use ($year, $attributes) {
            if ($this->editingEntryId !== null) {
                $entry = $this->findEntry($this->editingEntryId);
                $entry->update($attributes);

                return $entry;
            }

            CashBookYear::lockForUpdate()->findOrFail($year->cashBookYearID);

            return $year->entries()->create($attributes + [
                'receipt_number' => $year->nextReceiptNumber(),
                'created_by' => Auth::guard('web')->id(),
            ]);
        });

        $this->storeFiles($entry);

        $isNew = $this->editingEntryId === null;

        ActivityLogger::log(
            $isNew ? 'cash-book.create' : 'cash-book.update',
            ($isNew ? 'Buchung angelegt: ' : 'Buchung geändert: ')
                .'#'.$entry->receipt_number.' '.$entry->description,
            'cash_book_entry',
            $entry->cashBookEntryID
        );

        $this->closeEntryDialog();

        session()->flash(
            'success',
            $entry->type->label().' #'.$entry->receipt_number.($isNew ? ' erfasst.' : ' gespeichert.')
        );
    }

    public function deleteEntry(int $entryId): void
    {
        $this->openYearOrFail();

        $entry = $this->findEntry($entryId);

        foreach ($entry->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $entry->delete();

        ActivityLogger::log(
            'cash-book.delete',
            'Buchung gelöscht: #'.$entry->receipt_number.' '.$entry->description,
            'cash_book_entry',
            $entry->cashBookEntryID
        );

        $this->closeEntryDialog();

        session()->flash('success', 'Buchung #'.$entry->receipt_number.' gelöscht.');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $this->openYearOrFail();

        $attachment = CashBookAttachment::query()
            ->whereKey($attachmentId)
            ->whereHas('entry', fn ($query) => $query->where('cashBookYearID', $this->yearId))
            ->firstOrFail();

        Storage::disk('local')->delete($attachment->file_path);

        $attachment->delete();
    }

    public function render(): View
    {
        $years = CashBookYear::orderByDesc('start_date')->get();

        $selectedYear = $years->firstWhere('cashBookYearID', $this->yearId);

        $entries = collect();
        $totals = ['income' => 0.0, 'expense' => 0.0];
        $categories = collect();

        if ($selectedYear) {
            $totals = $selectedYear->totals();

            $categories = CashBookEntry::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');

            $entries = $selectedYear->entries()
                ->withCount('attachments')
                ->when(
                    $this->typeFilter !== 'all',
                    fn ($query) => $query->where('type', $this->typeFilter)
                )
                ->when(
                    $this->categoryFilter !== '',
                    fn ($query) => $query->where('category', $this->categoryFilter)
                )
                ->when(
                    trim($this->search) !== '',
                    function ($query) {
                        $search = '%'.trim($this->search).'%';

                        $query->where(function ($query) use ($search) {
                            $query->where('description', 'like', $search)
                                ->orWhere('category', 'like', $search)
                                ->orWhere('note', 'like', $search);

                            $receiptNumber = ltrim(trim($this->search), '#');

                            if (ctype_digit($receiptNumber)) {
                                $query->orWhere('receipt_number', (int) $receiptNumber);
                            }
                        });
                    }
                )
                ->orderByDesc('date')
                ->orderByDesc('receipt_number')
                ->get();
        }

        $editingEntry = $this->editingEntryId !== null
            ? CashBookEntry::with('attachments')->find($this->editingEntryId)
            : null;

        return view('livewire.cash-book.index', [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'entries' => $entries,
            'totals' => $totals,
            'balance' => $selectedYear
                ? round((float) $selectedYear->opening_balance + $totals['income'] - $totals['expense'], 2)
                : 0.0,
            'categories' => $categories,
            'editingEntry' => $editingEntry,
        ])->layout('layouts.app', [
            'title' => 'Kassabuch | VEMA',
            'heading' => 'Kassabuch',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function fileRules(): array
    {
        return ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic'];
    }

    private function storeFiles(CashBookEntry $entry): void
    {
        foreach ($this->files as $file) {
            $path = $file->store(
                'cash-book/'.$entry->cashBookYearID.'/'.$entry->cashBookEntryID,
                'local'
            );

            $entry->attachments()->create([
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        $this->files = [];
    }

    /**
     * Liefert das ausgewählte Vereinsjahr, sofern es noch offen ist —
     * serverseitige Sperre, unabhängig davon, was die Oberfläche anzeigt.
     */
    private function openYearOrFail(): CashBookYear
    {
        $year = CashBookYear::findOrFail($this->yearId);

        if ($year->isClosed()) {
            throw ValidationException::withMessages([
                'year' => 'Das Vereinsjahr '.$year->name.' ist abgeschlossen und kann nicht mehr geändert werden.',
            ]);
        }

        return $year;
    }

    private function findEntry(int $entryId): CashBookEntry
    {
        return CashBookEntry::query()
            ->where('cashBookYearID', $this->yearId)
            ->findOrFail($entryId);
    }

    private function resetEntryForm(): void
    {
        $this->reset([
            'editingEntryId',
            'type',
            'date',
            'amount',
            'description',
            'category',
            'note',
            'newFiles',
            'files',
        ]);

        $this->resetValidation();
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}

<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use App\Services\InventoryLoans;
use App\Services\InventoryScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Schnellausgabe: Mitglied wählen, dann Material hintereinander scannen —
 * mit Barcode-/NFC-Leser am PC (tippt den Code und Enter) oder mit der
 * Handykamera. Jedes gefundene Stück wird sofort ausgegeben.
 */
class Scan extends Component
{
    /**
     * Gleicher Code innerhalb dieser Zeit gilt als doppelter Scan (Leser prellt).
     */
    public const REPEAT_SECONDS = 2;

    #[Url(as: 'member')]
    public ?int $memberID = null;

    public string $memberSearch = '';

    public string $issuedOn = '';

    public string $dueOn = '';

    public string $code = '';

    /**
     * Ergebnis des letzten Scans für die Anzeige.
     *
     * @var array{status: string, message: string, code: string, itemID: ?int, loanID: ?int}|null
     */
    public ?array $lastResult = null;

    /**
     * Verlauf dieser Sitzung, neueste zuerst.
     *
     * @var list<array{action: string, itemID: int}>
     */
    public array $history = [];

    public ?string $lastScannedCode = null;

    public ?float $lastScannedAt = null;

    public function mount(): void
    {
        $this->issuedOn = now(config('app.club_timezone'))->toDateString();

        if ($this->memberID !== null && ! Member::whereKey($this->memberID)->exists()) {
            $this->memberID = null;
        }
    }

    public function selectMember(int $memberID): void
    {
        $this->memberID = Member::findOrFail($memberID)->memberID;
        $this->memberSearch = '';
        $this->history = [];
        $this->lastResult = null;
    }

    public function changeMember(): void
    {
        $this->memberID = null;
        $this->history = [];
        $this->lastResult = null;
    }

    public function scan(?string $scannedCode = null): void
    {
        $scanner = app(InventoryScanner::class);
        $loans = app(InventoryLoans::class);

        $raw = $scannedCode ?? $this->code;
        $this->code = '';

        $member = $this->memberID ? Member::find($this->memberID) : null;

        if ($member === null) {
            $this->result('error', 'Bitte zuerst ein Mitglied wählen.', $raw);

            return;
        }

        $this->validate([
            'issuedOn' => ['required', 'date'],
            'dueOn' => ['nullable', 'date', 'after_or_equal:issuedOn'],
        ], [], [
            'issuedOn' => 'Ausgegeben am',
            'dueOn' => 'Rückgabe bis',
        ]);

        $normalized = $scanner->normalize($raw);

        if ($normalized === '') {
            return;
        }

        if ($this->isRepeatedScan($normalized)) {
            return;
        }

        $item = $scanner->find($raw);

        if ($item === null) {
            $this->result('not_found', 'Kein Material mit dem Code „'.trim($raw).'“ gefunden.', $raw);

            return;
        }

        $currentLoan = $item->currentLoan()->with('member')->first();

        if ($currentLoan !== null && $currentLoan->memberID === $member->memberID) {
            $this->result('already', $item->label().' ist bereits bei '.$member->full_name.'.', $raw, $item, $currentLoan);

            return;
        }

        if ($currentLoan !== null) {
            $holder = $currentLoan->member?->full_name ?? 'Mitglied #'.$currentLoan->memberID;
            $this->result('error', $item->label().' ist bei '.$holder.' ausgegeben.', $raw, $item);

            return;
        }

        if ($item->condition === InventoryCondition::Retired) {
            $this->result('error', $item->label().' ist ausgeschieden und kann nicht ausgegeben werden.', $raw, $item);

            return;
        }

        try {
            $loans->issue($item, $member, $this->issuedOn, $this->dueOn, null, 'code');
        } catch (ValidationException $exception) {
            $this->result('error', $item->label().': '.collect($exception->errors())->flatten()->first(), $raw, $item);

            return;
        }

        array_unshift($this->history, ['action' => 'issued', 'itemID' => $item->itemID]);
        $this->result('issued', $item->label().' an '.$member->full_name.' ausgegeben.', $raw, $item);
    }

    public function returnItem(int $loanID, InventoryLoans $loans): void
    {
        $loan = InventoryLoan::query()
            ->whereKey($loanID)
            ->whereNull('returned_on')
            ->where('memberID', $this->memberID)
            ->firstOrFail();

        $loans->return($loan, now(config('app.club_timezone'))->toDateString());

        array_unshift($this->history, ['action' => 'returned', 'itemID' => $loan->itemID]);
        $this->result('returned', $loan->item->label().' wurde zurückgenommen.', '', $loan->item);
    }

    /**
     * Gleicher Code kurz hintereinander (z. B. prellender Leser) zählt einmal.
     */
    private function isRepeatedScan(string $normalized): bool
    {
        $now = microtime(true);
        $repeated = $this->lastScannedCode === $normalized
            && $this->lastScannedAt !== null
            && $now - $this->lastScannedAt < self::REPEAT_SECONDS;

        $this->lastScannedCode = $normalized;
        $this->lastScannedAt = $now;

        return $repeated;
    }

    private function result(string $status, string $message, string $code, ?InventoryItem $item = null, ?InventoryLoan $loan = null): void
    {
        $this->lastResult = [
            'status' => $status,
            'message' => $message,
            'code' => trim($code),
            'itemID' => $item?->itemID,
            'loanID' => $loan?->loanID,
        ];

        $this->dispatch('scan-result', ok: in_array($status, ['issued', 'returned'], true));
    }

    /**
     * @return Collection<int, Member>
     */
    private function memberMatches(): Collection
    {
        $search = trim($this->memberSearch);

        if ($search === '' || $this->memberID !== null) {
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

    public function render()
    {
        $member = $this->memberID ? Member::find($this->memberID) : null;
        $items = InventoryItem::query()->whereIn('itemID', array_column($this->history, 'itemID'))->get()->keyBy('itemID');

        return view('livewire.inventory.scan', [
            'member' => $member,
            'memberMatches' => $this->memberMatches(),
            'openLoans' => $member ? $member->openInventoryLoans()->with('item')->get() : collect(),
            'historyEntries' => collect($this->history)
                ->map(fn (array $entry) => [...$entry, 'item' => $items->get($entry['itemID'])])
                ->filter(fn (array $entry) => $entry['item'] !== null),
        ])->layout('layouts.app', [
            'title' => 'Schnellausgabe | VEMA',
            'heading' => 'Material',
        ]);
    }
}

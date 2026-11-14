<?php

namespace App\Services;

use App\Enums\CashBookEntryType;
use App\Mail\SepaPreNotificationMail;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;
use App\Models\SepaDebitItem;
use App\Models\SepaDebitRun;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sepa\Pain008Writer;
use App\Services\Sepa\SepaValidator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Lastschrift-Läufe für Mitgliedsbeiträge: Datei erstellen, nach der
 * Gutschrift bestätigen (erst dann bezahlt, optional mit Kassabuch-Buchung),
 * verwerfen und Rücklastschriften erfassen.
 */
class SepaDebitRuns
{
    public const CASH_BOOK_NONE = 'none';

    public const CASH_BOOK_SUMMARY = 'summary';

    public const CASH_BOOK_PER_MEMBER = 'per_member';

    public const CASH_BOOK_CATEGORY = 'Mitgliedsbeiträge';

    public function __construct(
        private Pain008Writer $writer,
        private SepaValidator $validator,
        private CashBookEntries $cashBook,
    ) {}

    /**
     * Was fehlt in den Einstellungen für einen Lauf? Leer = alles da.
     *
     * @return list<string>
     */
    public function missingSetup(): array
    {
        $setting = Setting::current();
        $missing = [];

        if (! $this->validator->isValidCreditorId($setting->sepa_creditor_id)) {
            $missing[] = 'Gläubiger-ID (Creditor-ID)';
        }

        if (! $this->validator->isValidIban($setting->iban)) {
            $missing[] = 'IBAN des Vereins';
        }

        return $missing;
    }

    /**
     * Offene Beiträge aktiver Mitglieder mit gültigem Mandat.
     *
     * @return Collection<int, MembershipFeeEntry>
     */
    public function candidates(MembershipFeeYear $year): Collection
    {
        return $this->openEntries($year)
            ->filter(fn (MembershipFeeEntry $entry) => $entry->member?->activeSepaMandate !== null)
            ->values();
    }

    /**
     * Offene Beiträge ohne Mandat (zur Information).
     *
     * @return Collection<int, MembershipFeeEntry>
     */
    public function withoutMandate(MembershipFeeYear $year): Collection
    {
        return $this->openEntries($year)
            ->filter(fn (MembershipFeeEntry $entry) => $entry->member?->activeSepaMandate === null)
            ->values();
    }

    /**
     * Frühestes Einzugsdatum: nächster Bankarbeitstag (Mo–Fr).
     */
    public function earliestCollectionDate(): CarbonImmutable
    {
        $date = CarbonImmutable::today()->addDay();

        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Neuer Lauf über die gewählten Beiträge; sie gehen in den Status
     * „im Einzug“.
     *
     * @param  list<int>  $entryIDs
     */
    public function create(MembershipFeeYear $year, array $entryIDs, CarbonInterface $collectionDate, ?User $user, bool $notifyMembers = false): SepaDebitRun
    {
        if ($missing = $this->missingSetup()) {
            throw ValidationException::withMessages(['sepa' => 'Bitte zuerst in den Einstellungen ergänzen: '.implode(', ', $missing).'.']);
        }

        if ($collectionDate->lt($this->earliestCollectionDate())) {
            throw ValidationException::withMessages(['collectionDate' => 'Das Einzugsdatum muss frühestens der nächste Bankarbeitstag ('.$this->earliestCollectionDate()->format('d.m.Y').') sein.']);
        }

        $entries = $this->candidates($year)->whereIn('entryID', $entryIDs)->values();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['sepa' => 'Bitte mindestens einen Beitrag mit Mandat auswählen.']);
        }

        $setting = Setting::current();

        $run = DB::transaction(function () use ($year, $entries, $collectionDate, $user, $setting) {
            $run = SepaDebitRun::create([
                'yearID' => $year->yearID,
                'collection_date' => $collectionDate->toDateString(),
                'message_id' => 'VEMA-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'status' => SepaDebitRun::STATUS_CREATED,
                'total_amount' => $entries->sum(fn (MembershipFeeEntry $entry) => (float) $entry->amount),
                'entry_count' => $entries->count(),
                'pain_version' => config('sepa.pain_version', Pain008Writer::VERSION_08),
                'created_by' => $user?->id,
            ]);

            $transactions = [];

            foreach ($entries as $entry) {
                $mandate = $entry->member->activeSepaMandate;
                $item = $run->items()->create([
                    'entryID' => $entry->entryID,
                    'mandateID' => $mandate->mandateID,
                    'amount' => $entry->amount,
                    'end_to_end_id' => 'VEMA-'.$run->runID.'-'.$entry->entryID,
                    'sequence_type' => $mandate->sequenceType(),
                ]);

                $transactions[] = [
                    'end_to_end_id' => $item->end_to_end_id,
                    'amount' => (string) $entry->amount,
                    'mandate_reference' => $mandate->reference,
                    'mandate_signed_on' => $mandate->signed_on,
                    'sequence_type' => $item->sequence_type,
                    'debtor_name' => $mandate->account_holder,
                    'debtor_iban' => $mandate->iban,
                    'debtor_bic' => $mandate->bic,
                    'remittance' => $this->remittance($year, $entry),
                ];

                $entry->update(['status' => MembershipFeeEntry::STATUS_PENDING]);
            }

            $xml = $this->writer->write($run->message_id, $collectionDate, [
                'name' => (string) $setting->name,
                'iban' => (string) $setting->iban,
                'bic' => $setting->bic,
                'creditor_id' => (string) $setting->sepa_creditor_id,
            ], $transactions, $run->pain_version);

            $path = 'sepa/lastschrift-'.$run->runID.'.xml';
            Storage::disk('local')->put($path, $xml);
            $run->update(['file_path' => $path]);

            return $run;
        });

        ActivityLogger::log(
            'sepa.run_created',
            'Lastschrift-Lauf #'.$run->runID.' ('.$run->entry_count.' Beiträge, '.$this->money($run->total_amount).', Einzug am '
                .$run->collection_date->format('d.m.Y').') wurde erstellt.',
            'SepaDebitRun',
            $run->runID
        );

        if ($notifyMembers) {
            $this->sendPreNotifications($run);
        }

        return $run;
    }

    /**
     * Gutschrift ist eingegangen: Beiträge gelten als bezahlt.
     *
     * @return string|null Hinweis (z. B. kein offenes Kassabuchjahr)
     */
    public function confirm(SepaDebitRun $run, ?User $user): ?string
    {
        $this->ensureOpen($run);

        $notice = DB::transaction(function () use ($run, $user) {
            $run->load('items.entry.member', 'items.mandate', 'year');

            foreach ($run->items as $item) {
                $item->entry?->update([
                    'status' => 'paid',
                    'paid_at' => $run->collection_date,
                    'payment_method' => 'sepa',
                ]);

                if ($item->mandate && $item->mandate->first_used_at === null) {
                    $item->mandate->update(['first_used_at' => $run->collection_date]);
                }
            }

            $run->update([
                'status' => SepaDebitRun::STATUS_CONFIRMED,
                'confirmed_by' => $user?->id,
                'confirmed_at' => now(),
            ]);

            return $this->bookCashBook($run);
        });

        ActivityLogger::log(
            'sepa.run_confirmed',
            'Lastschrift-Lauf #'.$run->runID.' wurde bestätigt; '.$run->entry_count.' Beiträge sind bezahlt.',
            'SepaDebitRun',
            $run->runID
        );

        return $notice;
    }

    /**
     * Lauf wurde nicht eingereicht: Beiträge wieder offen.
     */
    public function cancel(SepaDebitRun $run): void
    {
        $this->ensureOpen($run);

        DB::transaction(function () use ($run) {
            MembershipFeeEntry::query()
                ->whereIn('entryID', $run->items()->pluck('entryID'))
                ->where('status', MembershipFeeEntry::STATUS_PENDING)
                ->update(['status' => 'open']);

            $run->update(['status' => SepaDebitRun::STATUS_CANCELLED]);
        });

        ActivityLogger::log('sepa.run_cancelled', 'Lastschrift-Lauf #'.$run->runID.' wurde verworfen.', 'SepaDebitRun', $run->runID);
    }

    /**
     * Rücklastschrift nach der Bestätigung: Beitrag wieder offen.
     *
     * @return string|null Hinweis zum Kassabuch
     */
    public function markReturned(SepaDebitItem $item): ?string
    {
        $run = $item->run;

        if ($run->status !== SepaDebitRun::STATUS_CONFIRMED || $item->returned_at !== null) {
            throw ValidationException::withMessages(['sepa' => 'Für diesen Posten kann keine Rücklastschrift erfasst werden.']);
        }

        $entry = $item->entry;
        $mode = $this->cashBookMode();
        $notice = null;

        DB::transaction(function () use ($item, $entry, $mode, &$notice) {
            $item->update(['returned_at' => now()]);
            $entry?->update(['status' => 'open', 'paid_at' => null, 'payment_method' => null]);

            if ($mode === self::CASH_BOOK_PER_MEMBER) {
                $year = $this->cashBook->openYearFor(today());

                if ($year) {
                    $this->cashBook->create($year, [
                        'type' => CashBookEntryType::Expense,
                        'date' => today()->toDateString(),
                        'amount' => $item->amount,
                        'description' => $this->shorten('Rücklastschrift Mitgliedsbeitrag '.$entry?->member?->full_name),
                        'category' => self::CASH_BOOK_CATEGORY,
                    ]);
                } else {
                    $notice = 'Kein offenes Kassabuchjahr für heute – bitte die Rücklastschrift im Kassabuch selbst erfassen.';
                }
            } elseif ($mode === self::CASH_BOOK_SUMMARY) {
                $notice = 'Die Sammelbuchung im Kassabuch bitte um '.$this->money($item->amount).' korrigieren bzw. die Rücklastschrift dort als Ausgabe erfassen.';
            }
        });

        ActivityLogger::log(
            'sepa.item_returned',
            'Rücklastschrift für "'.$entry?->member?->full_name.'" ('.$this->money($item->amount).', Lauf #'.$run->runID.') wurde erfasst.',
            'SepaDebitRun',
            $run->runID
        );

        return $notice;
    }

    public function cashBookMode(): string
    {
        $mode = (string) Setting::current()->sepa_cash_book_mode;

        return in_array($mode, [self::CASH_BOOK_NONE, self::CASH_BOOK_SUMMARY, self::CASH_BOOK_PER_MEMBER], true)
            ? $mode
            : self::CASH_BOOK_SUMMARY;
    }

    /**
     * @return Collection<int, MembershipFeeEntry>
     */
    private function openEntries(MembershipFeeYear $year): Collection
    {
        return MembershipFeeEntry::query()
            ->with(['member.activeSepaMandate'])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->where('amount', '>', 0)
            ->whereHas('member', fn ($query) => $query->where('active', true))
            ->get()
            ->sortBy(fn (MembershipFeeEntry $entry) => $entry->member->surname.' '.$entry->member->name)
            ->values();
    }

    private function bookCashBook(SepaDebitRun $run): ?string
    {
        $mode = $this->cashBookMode();

        if ($mode === self::CASH_BOOK_NONE) {
            return null;
        }

        $year = $this->cashBook->openYearFor($run->collection_date);

        if (! $year) {
            return 'Kein offenes Kassabuchjahr für den '.$run->collection_date->format('d.m.Y').' – die Einnahmen bitte im Kassabuch selbst erfassen.';
        }

        $yearName = $run->year?->name ?? $run->year?->year;

        if ($mode === self::CASH_BOOK_SUMMARY) {
            $this->cashBook->create($year, [
                'type' => CashBookEntryType::Income,
                'date' => $run->collection_date->toDateString(),
                'amount' => $run->total_amount,
                'description' => $this->shorten('Mitgliedsbeiträge '.$yearName.' – SEPA-Lastschrift ('.$run->entry_count.' Beiträge)'),
                'category' => self::CASH_BOOK_CATEGORY,
                'note' => 'Lastschrift-Lauf #'.$run->runID,
            ]);

            return null;
        }

        foreach ($run->items as $item) {
            $this->cashBook->create($year, [
                'type' => CashBookEntryType::Income,
                'date' => $run->collection_date->toDateString(),
                'amount' => $item->amount,
                'description' => $this->shorten('Mitgliedsbeitrag '.$yearName.' '.$item->entry?->member?->full_name),
                'category' => self::CASH_BOOK_CATEGORY,
                'note' => 'SEPA-Lastschrift, Lauf #'.$run->runID,
            ]);
        }

        return null;
    }

    private function sendPreNotifications(SepaDebitRun $run): void
    {
        $run->load('items.entry.member.emails', 'items.mandate', 'year');

        foreach ($run->items as $item) {
            $email = $item->entry?->member?->emails->first()?->email;

            if (! $email) {
                continue;
            }

            $mail = new SepaPreNotificationMail($run, $item);
            $rawMessage = null;
            $mail->withSymfonyMessage(function ($message) use (&$rawMessage) {
                $rawMessage = $message->toString();
            });

            try {
                Mail::to($email)->send($mail);
            } catch (Throwable $exception) {
                Log::warning('Vorabinformation zur Lastschrift konnte nicht versendet werden.', ['itemID' => $item->itemID, 'error' => $exception->getMessage()]);

                continue;
            }

            if ($rawMessage) {
                try {
                    app(ImapSentMailService::class)->append($rawMessage);
                } catch (Throwable $exception) {
                    Log::warning('Vorabinformation zur Lastschrift konnte nicht im Gesendet-Ordner abgelegt werden.', ['error' => $exception->getMessage()]);
                }
            }
        }
    }

    private function ensureOpen(SepaDebitRun $run): void
    {
        if (! $run->isOpen()) {
            throw ValidationException::withMessages(['sepa' => 'Dieser Lauf ist bereits abgeschlossen.']);
        }
    }

    private function remittance(MembershipFeeYear $year, MembershipFeeEntry $entry): string
    {
        return 'Mitgliedsbeitrag '.($year->name ?: $year->year).' '.$entry->member->full_name;
    }

    private function shorten(string $text): string
    {
        return mb_strimwidth($text, 0, 255, '…');
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' €';
    }
}

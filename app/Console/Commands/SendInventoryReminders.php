<?php

namespace App\Console\Commands;

use App\Enums\Feature;
use App\Mail\InventoryReminderMail;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\InventoryReminderSent;
use App\Models\User;
use App\Services\ImapSentMailService;
use App\Services\PlanEntitlements;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInventoryReminders extends Command
{
    protected $signature = 'inventory:send-reminders';

    protected $description = 'Erinnert Admins und Zeugwarte an fällige Prüfungen und überfällige Rückgaben von Material';

    public function handle(PlanEntitlements $entitlements): int
    {
        if (! $entitlements->allows(Feature::Inventory)) {
            $this->info('Materialverwaltung ist im Paket dieses Vereins nicht enthalten.');

            return self::SUCCESS;
        }

        $inspections = InventoryItem::query()
            ->inspectionDue()
            ->with('category')
            ->orderBy('next_inspection_on')
            ->get();

        $returns = InventoryLoan::query()
            ->whereNull('returned_on')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today())
            ->with(['item', 'member'])
            ->orderBy('due_on')
            ->get();

        $newInspections = $inspections->reject(fn (InventoryItem $item) => $this->alreadySent('inspection', $item->itemID, $item->next_inspection_on->toDateString()));
        $newReturns = $returns->reject(fn (InventoryLoan $loan) => $this->alreadySent('return', $loan->loanID, $loan->due_on->toDateString()));

        // Nur erinnern, wenn etwas Neues dazugekommen ist; die Mail listet dann alles Fällige.
        if ($newInspections->isEmpty() && $newReturns->isEmpty()) {
            $this->info('Keine neuen fälligen Prüfungen oder Rückgaben.');

            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        if ($recipients->isEmpty()) {
            $this->warn('Kein Admin oder Zeugwart mit E-Mail-Adresse gefunden.');

            return self::SUCCESS;
        }

        $mail = new InventoryReminderMail($inspections, $returns);

        $rawMessage = null;
        $mail->withSymfonyMessage(function ($message) use (&$rawMessage) {
            $rawMessage = $message->toString();
        });

        Mail::to($recipients->all())->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $imapException) {
                Log::warning('Material-Erinnerung wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.', [
                    'error' => $imapException->getMessage(),
                ]);
            }
        }

        foreach ($newInspections as $item) {
            InventoryReminderSent::create(['kind' => 'inspection', 'reference_id' => $item->itemID, 'due_on' => $item->next_inspection_on->toDateString()]);
        }

        foreach ($newReturns as $loan) {
            InventoryReminderSent::create(['kind' => 'return', 'reference_id' => $loan->loanID, 'due_on' => $loan->due_on->toDateString()]);
        }

        $this->info('Material-Erinnerung an '.$recipients->count().' Empfänger verschickt.');

        return self::SUCCESS;
    }

    private function alreadySent(string $kind, int $referenceID, string $dueOn): bool
    {
        return InventoryReminderSent::query()
            ->where('kind', $kind)
            ->where('reference_id', $referenceID)
            ->whereDate('due_on', $dueOn)
            ->exists();
    }

    /**
     * @return Collection<int, string>
     */
    private function recipients(): Collection
    {
        return User::role(['admin', 'zeugwart'])
            ->where('enabled', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();
    }
}

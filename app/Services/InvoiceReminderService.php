<?php

namespace App\Services;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugt Zahlungserinnerung bzw. 1./2. Mahnung zu einer offenen Rechnung,
 * legt das PDF ab und hält den Versand in der Historie fest.
 */
class InvoiceReminderService
{
    public const EMAIL = 'email';

    public const POST = 'post';

    public function __construct(
        private readonly ImapSentMailService $sentMailService
    ) {}

    /**
     * @throws \RuntimeException mit verständlicher Meldung, wenn keine Mahnung möglich ist
     */
    public function send(Invoice $invoice, string $deliveryMethod): InvoiceReminder
    {
        $path = null;

        try {
            return DB::transaction(function () use ($invoice, $deliveryMethod, &$path) {
                $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->invoiceID);
                $locked->load(['recipient', 'items', 'reminders']);

                if ($locked->status !== 'sent') {
                    throw new \RuntimeException('Nur offene Rechnungen können angemahnt werden.');
                }

                $level = $locked->nextReminderLevel();

                if ($level === null) {
                    throw new \RuntimeException('Es wurden bereits alle Mahnstufen versendet.');
                }

                $email = $locked->recipient?->email;

                if ($deliveryMethod === self::EMAIL && ! $email) {
                    throw new \RuntimeException('Für diesen Empfänger ist keine E-Mail-Adresse hinterlegt.');
                }

                $filename = 'Rechnung_'.$locked->invoice_number.'_Stufe'.$level.'_'.now()->format('Ymd_His').'.pdf';
                $path = 'invoices/'.$locked->invoice_date->format('Y').'/reminders/'.$filename;

                Storage::disk('local')->put($path, $this->renderPdf($locked, $level));

                $reminder = InvoiceReminder::query()->create([
                    'invoiceID' => $locked->invoiceID,
                    'level' => $level,
                    'delivery_method' => $deliveryMethod,
                    'file_path' => $path,
                    'file_name' => $filename,
                    'sent_to' => $deliveryMethod === self::EMAIL ? $email : null,
                    'sent_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                if ($deliveryMethod === self::EMAIL) {
                    $this->sentMailService->sendAndAppend(
                        new InvoiceReminderMail($reminder->setRelation('invoice', $locked)),
                        $email,
                        ['invoiceID' => $locked->invoiceID, 'reminderLevel' => $level]
                    );
                }

                ActivityLogger::log(
                    $deliveryMethod === self::EMAIL ? 'invoice.reminder_sent' : 'invoice.reminder_printed',
                    $reminder->level_label.' zu Rechnung '.$locked->invoice_number
                        .($deliveryMethod === self::EMAIL ? ' wurde per E-Mail an '.$email.' versendet.' : ' wurde als PDF erzeugt.'),
                    'Invoice',
                    $locked->invoiceID
                );

                return $reminder;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    private function renderPdf(Invoice $invoice, int $level): string
    {
        $settings = Setting::current();

        $pdf = Pdf::loadView('pdf.invoice-reminder', [
            'invoice' => $invoice,
            'recipient' => $invoice->recipient,
            'settings' => $settings,
            'level' => $level,
            'levelLabel' => InvoiceReminder::LEVELS[$level],
            'paymentDeadline' => today()->addDays((int) $settings->invoice_reminder_interval_days),
        ])->setPaper('a4', 'portrait');

        return app(PdfLetterheadService::class)->applyClubLetterhead($pdf->output());
    }
}

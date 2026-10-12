<?php

namespace App\Http\Controllers;

use App\Mail\CircularMail;
use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Models\CircularRecipient;
use App\Models\Member;
use App\Services\ActivityLogger;
use App\Services\CircularPdfService;
use App\Services\ImapSentMailService;
use App\Services\PdfLetterheadService;
use App\Services\TemplateRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CircularController extends Controller
{
    public function previewRecipient(
        Circular $circular,
        Member $member,
        CircularPdfService $circularPdfService
    ) {
        return response(
            $circularPdfService->renderForMember($circular, $member),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$circularPdfService->fileNameFor($circular, $member).'"',
            ]
        );
    }

    public function downloadPostBatch(
        Circular $circular,
        TemplateRendererService $renderer,
        PdfLetterheadService $letterheadService
    ) {
        $circular->load([
            'recipients.member.city',
        ]);

        $postRecipients = $circular->recipients
            ->where('delivery_method', 'post');

        abort_if(
            $postRecipients->isEmpty(),
            404,
            'Keine Post-Empfänger vorhanden.'
        );

        $pages = [];

        foreach ($postRecipients as $recipient) {

            $member = $recipient->member;

            if (! $member) {
                continue;
            }

            $body = $renderer->circular(
                $circular->body_html ?? '',
                $member
            );

            $pages[] = [
                'member' => $member,
                'body' => $body,
            ];
        }

        abort_if(
            empty($pages),
            404,
            'Keine gültigen Post-Empfänger vorhanden.'
        );

        $pdf = Pdf::loadView(
            'pdf.circular-batch',
            [
                'circular' => $circular,
                'pages' => $pages,
            ]
        )->setPaper('a4');

        $finalPdf = $letterheadService->applyClubLetterhead(
            $pdf->output()
        );

        $fileName =
            'Rundschreiben_'
            .$circular->circularID
            .'_Post.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            ]
        );
    }

    public function sendTestMail(
        Circular $circular,
        CircularRecipient $recipient
    ) {
        abort_unless(
            $recipient->circularID === $circular->circularID,
            404
        );

        abort_unless(
            $recipient->delivery_method === 'email'
            && filled($recipient->email),
            422,
            'Dieser Empfänger hat keine gültige E-Mail-Adresse.'
        );

        $rawMessage = null;

        // Die Testmail geht bewusst nur an diese eine Person.
        $mail = new CircularMail(
            $circular,
            $recipient,
            new EloquentCollection([$recipient])
        );

        $mail->withSymfonyMessage(
            function ($message) use (&$rawMessage) {
                $rawMessage = $message->toString();
            }
        );

        Mail::to($recipient->email)->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append(
                    $rawMessage
                );
            } catch (\Throwable $imapException) {

                \Log::warning(
                    'Rundschreiben-Testmail wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                    [
                        'circularID' => $circular->circularID,
                        'recipientID' => $recipient->recipientID,
                        'email' => $recipient->email,
                        'error' => $imapException->getMessage(),
                    ]
                );
            }
        }

        ActivityLogger::log(
            'circular.test_mail_sent',
            'Testmail zum Rundschreiben „'.$circular->title.'“ wurde an „'.$recipient->display_name.'“ ('.$recipient->email.') versendet.',
            'Circular',
            $circular->circularID
        );

        return back()->with(
            'success',
            'Testmail wurde an '
            .$recipient->display_name
            .' ('.$recipient->email.') versendet.'
        );
    }

    public function sendAllEmails(Circular $circular)
    {
        $circular->load([
            'recipients',
            'attachments',
        ]);

        /*
         * Nur noch nicht versendete E-Mail-Empfänger.
         */
        $emailRecipients = $circular->recipients
            ->where('delivery_method', 'email')
            ->whereNotNull('email')
            ->whereNull('sent_at');

        abort_if(
            $emailRecipients->isEmpty(),
            422,
            'Keine noch offenen E-Mail-Empfänger vorhanden.'
        );

        $sentCount = 0;
        $failedCount = 0;
        $mailCount = 0;

        /*
         * Empfänger mit derselben E-Mail-Adresse (z. B. Familien) bekommen
         * gemeinsam eine Nachricht, in der jede Person angesprochen wird —
         * statt mehrerer gleicher E-Mails. Versendet gilt sie für alle.
         */
        $households = $emailRecipients
            ->groupBy(fn (CircularRecipient $recipient) => CircularRecipient::normalizeEmail($recipient->email));

        foreach ($households as $householdRecipients) {

            $household = new EloquentCollection($householdRecipients->values()->all());
            $recipient = $household->first();

            try {

                $rawMessage = null;

                $mail = new CircularMail(
                    $circular,
                    $recipient,
                    $household
                );

                $mail->withSymfonyMessage(
                    function ($message) use (&$rawMessage) {
                        $rawMessage = $message->toString();
                    }
                );

                /*
                 * 1. E-Mail tatsächlich versenden
                 */
                Mail::to($recipient->email)->send($mail);

                /*
                 * Ab hier gilt die Mail für alle Personen dieser Adresse als versendet.
                 */
                CircularRecipient::query()
                    ->whereIn('recipientID', $household->modelKeys())
                    ->update(['sent_at' => now()]);

                $sentCount += $household->count();
                $mailCount++;

                /*
                 * 2. Kopie zusätzlich per IMAP
                 *    im Gesendet-Ordner speichern.
                 *
                 * Ein Fehler hier darf den eigentlichen
                 * Versand NICHT als fehlgeschlagen markieren.
                 */
                if ($rawMessage) {
                    try {
                        app(ImapSentMailService::class)->append(
                            $rawMessage
                        );
                    } catch (\Throwable $imapException) {

                        \Log::warning(
                            'Rundschreiben wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                            [
                                'circularID' => $circular->circularID,
                                'recipientIDs' => $household->modelKeys(),
                                'email' => $recipient->email,
                                'error' => $imapException->getMessage(),
                            ]
                        );
                    }
                }

            } catch (\Throwable $e) {

                $failedCount += $household->count();

                \Log::error(
                    'Rundschreiben konnte nicht per E-Mail versendet werden.',
                    [
                        'circularID' => $circular->circularID,
                        'recipientIDs' => $household->modelKeys(),
                        'email' => $recipient->email,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        /*
         * Status erst auf "sent" setzen,
         * wenn wirklich alle Empfänger erledigt sind.
         *
         * Post-Empfänger bleiben vorerst offen,
         * bis wir deren Versand ebenfalls bestätigen.
         */
        $hasOpenRecipients = $circular->recipients()
            ->whereNull('sent_at')
            ->exists();

        if (! $hasOpenRecipients) {

            $circular->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        ActivityLogger::log(
            'circular.emails_sent',
            'Rundschreiben „'.$circular->title.'“ wurde an '.$sentCount.' Empfänger per E-Mail versendet'
                .$this->sharedAddressNote($sentCount, $mailCount).'.'
                .($failedCount > 0 ? ' '.$failedCount.' Empfänger konnten nicht erreicht werden.' : ''),
            'Circular',
            $circular->circularID
        );

        if ($failedCount > 0) {

            return back()->with(
                'warning',
                $sentCount
                .' Empfänger erfolgreich per E-Mail erreicht'
                .$this->sharedAddressNote($sentCount, $mailCount).'. '
                .$failedCount
                .' Empfänger konnten nicht erreicht werden.'
            );
        }

        return back()->with(
            'success',
            'Rundschreiben wurde an '
            .$sentCount
            .' Empfänger per E-Mail versendet'
            .$this->sharedAddressNote($sentCount, $mailCount).'.'
        );
    }

    /**
     * Hinweis, wenn mehrere Empfänger eine gemeinsame E-Mail bekommen haben,
     * z. B. „ (5 E-Mails, gemeinsame Adressen zusammengefasst)“.
     */
    private function sharedAddressNote(int $recipientCount, int $mailCount): string
    {
        if ($mailCount === 0 || $mailCount === $recipientCount) {
            return '';
        }

        return ' ('.$mailCount.' '.($mailCount === 1 ? 'E-Mail' : 'E-Mails').', gemeinsame Adressen zusammengefasst)';
    }

    public function markPostAsSent(Circular $circular)
    {
        $postRecipients = $circular->recipients()
            ->where('delivery_method', 'post')
            ->whereNull('sent_at')
            ->get();

        abort_if(
            $postRecipients->isEmpty(),
            422,
            'Keine offenen Post-Empfänger vorhanden.'
        );

        foreach ($postRecipients as $recipient) {
            $recipient->update([
                'sent_at' => now(),
            ]);
        }

        $hasOpenRecipients = $circular->recipients()
            ->whereNull('sent_at')
            ->exists();

        if (! $hasOpenRecipients) {
            $circular->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        ActivityLogger::log(
            'circular.post_marked_sent',
            'Postversand des Rundschreibens „'.$circular->title.'“ an '.$postRecipients->count().' Empfänger wurde als erledigt markiert.',
            'Circular',
            $circular->circularID
        );

        return back()->with(
            'success',
            $postRecipients->count()
            .' Post-Empfänger wurden als versendet markiert.'
        );
    }

    public function showEmail(
        Circular $circular,
        CircularRecipient $recipient,
        TemplateRendererService $renderer
    ) {
        abort_unless(
            $recipient->circularID === $circular->circularID,
            404
        );

        abort_unless(
            $recipient->delivery_method === 'email',
            404
        );

        $recipient->loadMissing([
            'member.city',
            'externalContact',
        ]);

        $circular->loadMissing([
            'attachments',
        ]);

        // So, wie die E-Mail verschickt wird: gemeinsam mit allen Empfängern derselben Adresse.
        $body = $renderer->circularForRecipients(
            $circular->body_html ?? '',
            $recipient->emailHousehold()->map->addressee()->filter()
        );

        return view(
            'livewire.circulars.email-preview',
            [
                'circular' => $circular,
                'recipient' => $recipient,
                'member' => $recipient->member,
                'body' => $body,
            ]
        );
    }

    public function showAttachment(
        CircularAttachment $attachment
    ) {
        abort_unless(
            $attachment->file_path
            && Storage::disk('local')->exists($attachment->file_path),
            404
        );

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name
        );
    }
}

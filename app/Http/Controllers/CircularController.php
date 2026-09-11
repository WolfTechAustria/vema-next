<?php

namespace App\Http\Controllers;

use App\Models\Circular;
use App\Models\Member;
use App\Services\PdfLetterheadService;
use App\Services\TemplateRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\CircularMail;
use App\Models\CircularRecipient;
use Illuminate\Support\Facades\Mail;
use App\Models\CircularAttachment;
use Illuminate\Support\Facades\Storage;

class CircularController extends Controller
{
    public function previewRecipient(
        Circular $circular,
        Member $member,
        TemplateRendererService $renderer,
        PdfLetterheadService $letterheadService
    ) {
        $member->loadMissing('city');

        $body = $renderer->circular(
            $circular->body_html ?? '',
            $member
        );

        $pdf = Pdf::loadView(
            'pdf.circular',
            [
                'circular' => $circular,
                'member' => $member,
                'body' => $body,
            ]
        )->setPaper('a4');

        $contentPdf = $pdf->output();

        $contentPath = tempnam(
                sys_get_temp_dir(),
                'circular_'
            ) . '.pdf';

        file_put_contents(
            $contentPath,
            $contentPdf
        );

        $letterheadPath = storage_path(
            'app/templates/briefpapier.pdf'
        );

        try {
            $finalPdf = $letterheadService->apply(
                $contentPath,
                $letterheadPath
            );
        } finally {
            if (is_file($contentPath)) {
                unlink($contentPath);
            }
        }

        $fileName =
            'Rundschreiben_'
            . $circular->circularID
            . '_'
            . $member->memberID
            . '.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'inline; filename="' . $fileName . '"',
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

            if (!$member) {
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

        $contentPath = tempnam(
                sys_get_temp_dir(),
                'circular_batch_'
            ) . '.pdf';

        file_put_contents(
            $contentPath,
            $pdf->output()
        );

        $letterheadPath = storage_path(
            'app/templates/briefpapier.pdf'
        );

        try {
            $finalPdf = $letterheadService->apply(
                $contentPath,
                $letterheadPath
            );
        } finally {
            if (is_file($contentPath)) {
                unlink($contentPath);
            }
        }

        $fileName =
            'Rundschreiben_'
            . $circular->circularID
            . '_Post.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'inline; filename="' . $fileName . '"',
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

        Mail::to($recipient->email)->send(
            new CircularMail(
                $circular,
                $recipient
            )
        );

        return back()->with(
            'success',
            'Testmail wurde an ' . $recipient->email . ' versendet.'
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

        foreach ($emailRecipients as $recipient) {

            try {

                Mail::to($recipient->email)->send(
                    new CircularMail(
                        $circular,
                        $recipient
                    )
                );

                $recipient->update([
                    'sent_at' => now(),
                ]);

                $sentCount++;

            } catch (\Throwable $e) {

                $failedCount++;

                \Log::error(
                    'Rundschreiben konnte nicht per E-Mail versendet werden.',
                    [
                        'circularID' => $circular->circularID,
                        'recipientID' => $recipient->recipientID,
                        'memberID' => $recipient->memberID,
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

        if (!$hasOpenRecipients) {

            $circular->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        if ($failedCount > 0) {

            return back()->with(
                'warning',
                $sentCount
                . ' E-Mail(s) erfolgreich versendet. '
                . $failedCount
                . ' E-Mail(s) konnten nicht versendet werden.'
            );
        }

        return back()->with(
            'success',
            $sentCount
            . ' Rundschreiben wurden erfolgreich per E-Mail versendet.'
        );
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

        if (!$hasOpenRecipients) {
            $circular->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }

        return back()->with(
            'success',
            $postRecipients->count()
            . ' Post-Empfänger wurden als versendet markiert.'
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
        ]);

        $circular->loadMissing([
            'attachments',
        ]);

        $body = $renderer->circular(
            $circular->body_html ?? '',
            $recipient->member
        );

        return view(
            'circulars.email-preview',
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

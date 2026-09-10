<?php

namespace App\Http\Controllers;

use App\Models\MembershipFeeEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Template;
use App\Services\TemplateRendererService;
use App\Services\PdfLetterheadService;

class MembershipFeePrescriptionController extends Controller
{
    public function download(MembershipFeeEntry $entry)
    {
        $template = Template::query()
            ->where(
                'key',
                'membership_fee_prescription'
            )
            ->where('active', true)
            ->first();

        if (!$template) {
            dd('Template membership_fee_prescription wurde nicht gefunden.');
        }

        $body = app(
            TemplateRendererService::class
        )->membershipFeePrescription(
            $template,
            $entry
        );

        $entry->load([
            'member.city',
            'member.emails',
            'year',
        ]);

        $member = $entry->member;
        $year = $entry->year;

        abort_unless($member && $year, 404);

        /*
         * Individueller Betrag hat Vorrang.
         * Falls keiner gesetzt ist, Standardbetrag des Jahres verwenden.
         */
        $amount = $entry->amount ?? $year->default_amount;

        /*
         * Bestehende Legacy-Anrede berücksichtigen.
         */
        $gender = mb_strtolower(trim((string) $member->gender));

        $salutation = in_array(
            $gender,
            ['herr', 'm', 'male', 'männlich'],
            true
        )
            ? 'lieber'
            : 'liebe';

        $email = $member->emails->first()?->email;

        $pdf = Pdf::loadView('pdf.membership-fee-prescription', [
            'entry' => $entry,
            'member' => $member,
            'year' => $year,
            'amount' => $amount,
            'salutation' => $salutation,
            'email' => $email,
            'template' => $template,
            'body' => $body,
        ])->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory
            . '/prescription_'
            . $entry->entryID
            . '.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $letterheadPdf = storage_path(
            'app/templates/briefpapier.pdf'
        );

        $finalPdf = app(PdfLetterheadService::class)
            ->apply(
                $tempPdf,
                $letterheadPdf
            );

        @unlink($tempPdf);

        $filename = sprintf(
            '%d_Mitgliedsbeitragsvorschreibung_%d.pdf',
            $member->memberID,
            $year->year
        );

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'attachment; filename="' . $filename . '"',
            ]
        );
    }

    public function preview()
    {
        $entry = \App\Models\MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->orderByDesc('entryID')
            ->firstOrFail();

        $member = $entry->member;
        $year = $entry->year;

        $template = \App\Models\Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $body = app(
            \App\Services\TemplateRendererService::class
        )->membershipFeePrescription(
            $template,
            $entry
        );

        $amount = $entry->amount
            ?? $year->default_amount;

        $gender = mb_strtolower(
            trim((string) $member->gender)
        );

        $salutation = in_array(
            $gender,
            ['herr', 'm', 'male', 'männlich'],
            true
        )
            ? 'lieber'
            : 'liebe';

        $email = $member->emails->first()?->email;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.membership-fee-prescription',
            [
                'entry' => $entry,
                'member' => $member,
                'year' => $year,
                'amount' => $amount,
                'salutation' => $salutation,
                'email' => $email,
                'template' => $template,
                'body' => $body,
            ]
        )->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory . '/preview_membership_fee.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $letterheadPdf = storage_path(
            'app/templates/briefpapier.pdf'
        );

        $finalPdf = app(
            \App\Services\PdfLetterheadService::class
        )->apply(
            $tempPdf,
            $letterheadPdf
        );

        @unlink($tempPdf);

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Vorschau.pdf"',
            ]
        );
    }


    public function downloadAll(
    \App\Models\MembershipFeeYear $year
    ) {
        $entries = \App\Models\MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) =>
                ($entry->member?->surname ?? '')
                . ' '
                . ($entry->member?->name ?? '')
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine offenen Beiträge vorhanden.'
        );

        $template = \App\Models\Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            \App\Services\TemplateRendererService::class
        );

        $pages = $entries->map(function ($entry) use (
            $renderer,
            $template,
            $year
        ) {
            $member = $entry->member;

            $gender = mb_strtolower(
                trim((string) $member->gender)
            );

            $salutation = in_array(
                $gender,
                ['herr', 'm', 'male', 'männlich'],
                true
            )
                ? 'lieber'
                : 'liebe';

            return [
                'entry' => $entry,
                'member' => $member,
                'year' => $year,

                'amount' =>
                    $entry->amount
                    ?? $year->default_amount,

                'salutation' => $salutation,

                'email' =>
                    $member->emails
                        ->first()?->email,

                'body' =>
                    $renderer
                        ->membershipFeePrescription(
                            $template,
                            $entry
                        ),
            ];
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory
            . '/membership_fee_batch_'
            . $year->year
            . '.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $letterheadPdf = storage_path(
            'app/templates/briefpapier.pdf'
        );

        $finalPdf = app(
            \App\Services\PdfLetterheadService::class
        )->apply(
            $tempPdf,
            $letterheadPdf
        );

        @unlink($tempPdf);

        $filename =
            'Beitragsvorschreibungen_'
            . $year->year
            . '.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',

                'Content-Disposition' =>
                    'attachment; filename="'
                    . $filename
                    . '"',
            ]
        );
    }

    public function downloadSelected(\Illuminate\Http\Request $request)
    {
        $entryIds = collect(
            explode(',', (string) $request->query('entries'))
        )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        abort_if(
            $entryIds->isEmpty(),
            404,
            'Keine Beiträge ausgewählt.'
        );

        $entries = \App\Models\MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->whereIn('entryID', $entryIds)
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) =>
                ($entry->member?->surname ?? '')
                . ' '
                . ($entry->member?->name ?? '')
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine gültigen Beiträge gefunden.'
        );

        $template = \App\Models\Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            \App\Services\TemplateRendererService::class
        );

        $pages = $entries->map(function ($entry) use (
            $renderer,
            $template
        ) {
            $member = $entry->member;
            $year = $entry->year;

            $gender = mb_strtolower(
                trim((string) $member->gender)
            );

            $salutation = in_array(
                $gender,
                ['herr', 'm', 'male', 'männlich'],
                true
            )
                ? 'lieber'
                : 'liebe';

            return [
                'entry' => $entry,
                'member' => $member,
                'year' => $year,
                'amount' =>
                    $entry->amount
                    ?? $year->default_amount,
                'salutation' => $salutation,
                'email' =>
                    $member->emails->first()?->email,
                'body' =>
                    $renderer->membershipFeePrescription(
                        $template,
                        $entry
                    ),
            ];
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory
            . '/membership_fee_selected_'
            . uniqid()
            . '.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $letterheadPdf = storage_path(
            'app/templates/briefpapier.pdf'
        );

        $finalPdf = app(
            \App\Services\PdfLetterheadService::class
        )->apply(
            $tempPdf,
            $letterheadPdf
        );

        @unlink($tempPdf);

        $filename = 'Beitragsvorschreibungen_Auswahl.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'attachment; filename="' . $filename . '"',
            ]
        );
    }
    public function showStoredPdf(
        \App\Models\MembershipFeePrescription $prescription
    ) {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        abort_unless(
            $disk->exists($prescription->file_path),
            404,
            'PDF-Datei nicht gefunden.'
        );

        return response(
            $disk->get($prescription->file_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'inline; filename="' . $prescription->file_name . '"',
            ]
        );
    }

    public function downloadSelectedReminders(
        \Illuminate\Http\Request $request
    ) {
        $entryIds = collect(
            explode(',', (string) $request->query('entries'))
        )
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        abort_if(
            $entryIds->isEmpty(),
            404,
            'Keine Beiträge ausgewählt.'
        );

        $entries = \App\Models\MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
                'prescriptions',
            ])
            ->whereIn('entryID', $entryIds)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get()
            ->filter(fn ($entry) =>
            $entry->isReminderDue()
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine fälligen Erinnerungen gefunden.'
        );

        $template = \App\Models\Template::query()
            ->where('key', 'membership_fee_reminder')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            \App\Services\TemplateRendererService::class
        );

        $pages = $entries->map(function ($entry) use (
            $renderer,
            $template
        ) {
            $member = $entry->member;
            $year = $entry->year;

            $lastReminderLevel = $entry->prescriptions
                ->where('type', 'reminder')
                ->max('reminder_level');

            $nextReminderLevel =
                ($lastReminderLevel ?? 0) + 1;

            return [
                'entry' => $entry,
                'member' => $member,
                'year' => $year,

                'amount' =>
                    $entry->amount
                    ?? $year->default_amount,

                'email' =>
                    $member->emails->first()?->email,

                'reminderLevel' =>
                    $nextReminderLevel,

                'body' =>
                    $renderer->membershipFeeReminder(
                        $template,
                        $entry,
                        $nextReminderLevel
                    ),
            ];
        });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory
            . '/membership_fee_reminders_'
            . uniqid()
            . '.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $finalPdf = app(
            \App\Services\PdfLetterheadService::class
        )->apply(
            $tempPdf,
            storage_path('app/templates/briefpapier.pdf')
        );

        @unlink($tempPdf);

        $tempFinalPdf = $tempDirectory
            . '/final_reminders_'
            . uniqid()
            . '.pdf';

        file_put_contents(
            $tempFinalPdf,
            $finalPdf
        );

        foreach ($pages->values() as $index => $pageData) {

            $entry = $pageData['entry'];
            $member = $pageData['member'];
            $year = $pageData['year'];
            $reminderLevel = $pageData['reminderLevel'];

            /*
             * Die entsprechende Seite aus dem Sammel-PDF
             * als eigenes PDF herauslösen.
             */
            $singlePdf = new \setasign\Fpdi\Fpdi();

            $singlePdf->setSourceFile($tempFinalPdf);

            $templateId = $singlePdf->importPage(
                $index + 1
            );

            $size = $singlePdf->getTemplateSize(
                $templateId
            );

            $singlePdf->AddPage(
                $size['width'] > $size['height']
                    ? 'L'
                    : 'P',
                [
                    $size['width'],
                    $size['height'],
                ]
            );

            $singlePdf->useTemplate(
                $templateId,
                0,
                0,
                $size['width'],
                $size['height']
            );

            $singlePdfContent = $singlePdf->Output(
                'S'
            );

            /*
             * Dateiname der archivierten Erinnerung.
             */
            $filename =
                'Mitgliedsbeitrag_'
                . $year->year
                . '_Erinnerung_'
                . $reminderLevel
                . '_'
                . $member->surname
                . '_'
                . $member->memberID
                . '.pdf';

            $storagePath =
                'membership-fees/'
                . $year->year
                . '/'
                . $member->memberID
                . '/'
                . now()->format('Ymd_His')
                . '_'
                . $filename;

            \Illuminate\Support\Facades\Storage::disk('local')->put(
                $storagePath,
                $singlePdfContent
            );

            /*
             * Historieneintrag für Postversand.
             */
            \App\Models\MembershipFeePrescription::create([
                'entryID' => $entry->entryID,
                'memberID' => $member->memberID,
                'yearID' => $year->yearID,

                'type' => 'reminder',
                'reminder_level' => $reminderLevel,

                'delivery_method' => 'post',

                'file_path' => $storagePath,
                'file_name' => $filename,

                'sent_to' => null,
                'sent_at' => now(),
            ]);
        }

        @unlink($tempFinalPdf);

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'attachment; filename="Mitgliedsbeitrag_Erinnerungen.pdf"',
            ]
        );
    }

    public function downloadOpenOverview(
        \App\Models\MembershipFeeYear $year
    ) {
        $entries = \App\Models\MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'prescriptions',
                'year',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) =>
            $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) =>
                ($entry->member?->surname ?? '')
                . ' '
                . ($entry->member?->name ?? '')
            )
            ->values();

        $openAmount = $entries->sum(
            fn ($entry) => (float) ($entry->amount ?? 0)
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.membership-fees-open-overview',
            [
                'year' => $year,
                'entries' => $entries,
                'openAmount' => $openAmount,
            ]
        )->setPaper('a4', 'portrait');

        return $pdf->download(
            'Offene_Mitgliedsbeiträge_' . $year->year . '.pdf'
        );
    }


}

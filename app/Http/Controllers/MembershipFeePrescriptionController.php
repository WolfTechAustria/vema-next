<?php

namespace App\Http\Controllers;

use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeePrescription;
use App\Models\MembershipFeeYear;
use App\Models\Template;
use App\Services\PdfLetterheadService;
use App\Services\TemplateRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

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

        if (! $template) {
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

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

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
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    public function preview(Template $template)
    {
        $entry = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->orderByDesc('entryID')
            ->firstOrFail();

        $member = $entry->member;
        $year = $entry->year;

        $renderer = app(
            TemplateRendererService::class
        );

        if ($template->key === 'membership_fee_reminder') {

            $reminderLevel = 1;

            $body = $renderer->membershipFeeReminder(
                $template,
                $entry,
                $reminderLevel
            );

        } else {

            $reminderLevel = null;

            $body = $renderer->membershipFeePrescription(
                $template,
                $entry
            );
        }

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

        $pdf = Pdf::loadView(
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
                'reminderLevel' => $reminderLevel,
            ]
        )->setPaper('a4', 'portrait');

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

        $previewFilename = $template->key === 'membership_fee_reminder'
            ? 'Vorschau_Erinnerung.pdf'
            : 'Vorschau_Beitragsvorschreibung.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$previewFilename.'"',
            ]
        );
    }

    public function downloadAll(
        MembershipFeeYear $year
    ) {
        $entries = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) => ($entry->member?->surname ?? '')
                .' '
                .($entry->member?->name ?? '')
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine offenen Beiträge vorhanden.'
        );

        $template = Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            TemplateRendererService::class
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

                'amount' => $entry->amount
                    ?? $year->default_amount,

                'salutation' => $salutation,

                'email' => $member->emails
                    ->first()?->email,

                'body' => $renderer
                    ->membershipFeePrescription(
                        $template,
                        $entry
                    ),
            ];
        });

        $pdf = Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

        $filename =
            'Beitragsvorschreibungen_'
            .$year->year
            .'.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',

                'Content-Disposition' => 'attachment; filename="'
                    .$filename
                    .'"',
            ]
        );
    }

    public function downloadSelected(Request $request)
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

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
            ])
            ->whereIn('entryID', $entryIds)
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) => ($entry->member?->surname ?? '')
                .' '
                .($entry->member?->name ?? '')
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine gültigen Beiträge gefunden.'
        );

        $template = Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            TemplateRendererService::class
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
                'amount' => $entry->amount
                    ?? $year->default_amount,
                'salutation' => $salutation,
                'email' => $member->emails->first()?->email,
                'body' => $renderer->membershipFeePrescription(
                    $template,
                    $entry
                ),
            ];
        });

        $pdf = Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

        $filename = 'Beitragsvorschreibungen_Auswahl.pdf';

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    public function showStoredPdf(
        MembershipFeePrescription $prescription
    ) {
        $disk = Storage::disk('local');

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
                'Content-Disposition' => 'inline; filename="'.$prescription->file_name.'"',
            ]
        );
    }

    public function downloadSelectedReminders(
        Request $request
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

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
                'prescriptions',
            ])
            ->whereIn('entryID', $entryIds)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->get()
            ->filter(fn ($entry) => $entry->isReminderDue()
            )
            ->values();

        abort_if(
            $entries->isEmpty(),
            404,
            'Keine fälligen Erinnerungen gefunden.'
        );

        $template = Template::query()
            ->where('key', 'membership_fee_reminder')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(
            TemplateRendererService::class
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

                'amount' => $entry->amount
                    ?? $year->default_amount,

                'email' => $member->emails->first()?->email,

                'reminderLevel' => $nextReminderLevel,

                'body' => $renderer->membershipFeeReminder(
                    $template,
                    $entry,
                    $nextReminderLevel
                ),
            ];
        });

        $pdf = Pdf::loadView(
            'pdf.membership-fee-prescriptions-batch',
            [
                'pages' => $pages,
                'template' => $template,
            ]
        )->setPaper('a4', 'portrait');

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

        foreach ($pages->values() as $index => $pageData) {

            $entry = $pageData['entry'];
            $member = $pageData['member'];
            $year = $pageData['year'];
            $reminderLevel = $pageData['reminderLevel'];

            /*
             * Die entsprechende Seite aus dem Sammel-PDF
             * als eigenes PDF herauslösen.
             */
            $singlePdf = new Fpdi;

            $singlePdf->setSourceFile(
                StreamReader::createByString($finalPdf)
            );

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
                .$year->year
                .'_Erinnerung_'
                .$reminderLevel
                .'_'
                .$member->surname
                .'_'
                .$member->memberID
                .'.pdf';

            $storagePath =
                'membership-fees/'
                .$year->year
                .'/'
                .$member->memberID
                .'/'
                .now()->format('Ymd_His')
                .'_'
                .$filename;

            Storage::disk('local')->put(
                $storagePath,
                $singlePdfContent
            );

            /*
             * Historieneintrag für Postversand.
             */
            MembershipFeePrescription::create([
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

        return response(
            $finalPdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Mitgliedsbeitrag_Erinnerungen.pdf"',
            ]
        );
    }

    public function downloadOpenOverview(
        MembershipFeeYear $year
    ) {
        $entries = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'prescriptions',
                'year',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->get()
            ->sortBy(fn ($entry) => ($entry->member?->surname ?? '')
                .' '
                .($entry->member?->name ?? '')
            )
            ->values();

        $openAmount = $entries->sum(
            fn ($entry) => (float) ($entry->amount ?? 0)
        );

        $pdf = Pdf::loadView(
            'pdf.membership-fees-open-overview',
            [
                'year' => $year,
                'entries' => $entries,
                'openAmount' => $openAmount,
            ]
        )->setPaper('a4', 'portrait');

        return $pdf->download(
            'Offene_Mitgliedsbeiträge_'.$year->year.'.pdf'
        );
    }
}

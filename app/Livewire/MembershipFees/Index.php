<?php

namespace App\Livewire\MembershipFees;

use App\Mail\MembershipFeePrescriptionMail;
use App\Models\Member;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeePrescription;
use App\Models\MembershipFeeYear;
use App\Models\Template;
use App\Services\ImapSentMailService;
use App\Services\PdfLetterheadService;
use App\Services\TemplateRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $statusFilter = 'all';

    #[Url]
    public string $reminderFilter = 'all';

    public int $firstReminderAfterDays = 14;

    public int $reminderIntervalDays = 14;

    public int $maxReminders = 3;

    public bool $showReminderConfirmDialog = false;

    public bool $showResendDialog = false;

    public ?int $resendEntryID = null;

    #[Url]
    public string $emailFilter = 'all';

    public array $selectedEntries = [];

    public bool $showCreateYear = false;

    public ?int $newYear = null;

    public string $newYearName = '';

    public ?string $newYearAmount = null;

    public ?string $newYearDueDate = null;

    public ?string $defaultAmount = null;

    public ?int $yearId = null;

    public string $search = '';

    public function mount(): void
    {
        $year = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->first();

        $this->yearId = $year?->yearID;

        $this->updatedYearId();
    }

    public function downloadSelected()
    {
        if (empty($this->selectedEntries)) {
            $this->addError(
                'selectedEntries',
                'Bitte mindestens einen Beitrag auswählen.'
            );

            return;
        }

        $ids = implode(',', array_map(
            'intval',
            $this->selectedEntries
        ));

        return redirect()->route(
            'membership-fees.prescriptions.selected',
            [
                'entries' => $ids,
            ]
        );
    }

    public function requestSelectedReminders(): void
    {
        $summary = $this->reminderSelectionSummary();

        if ($summary['sendable'] === 0) {
            session()->flash(
                'error',
                'Keine ausgewählten Beiträge können erinnert werden.'
            );

            return;
        }

        $this->showReminderConfirmDialog = true;
    }

    public function selectAllOpen(): void
    {
        if (! $this->yearId) {
            return;
        }

        $this->selectedEntries = MembershipFeeEntry::query()
            ->where('yearId', $this->yearId)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->when(
                $this->emailFilter === 'with_email',
                fn ($query) => $query->whereHas('member.emails')
            )
            ->when(
                $this->emailFilter === 'without_email',
                fn ($query) => $query->whereDoesntHave('member.emails')
            )
            ->pluck('entryID')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function countSelectedWithEmail(): int
    {
        if (empty($this->selectedEntries)) {
            return 0;
        }

        return MembershipFeeEntry::query()
            ->whereIn('entryID', $this->selectedEntries)
            ->whereHas('member.emails')
            ->count();
    }

    public function sendSelectedByEmail(): void
    {
        $entries = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
                'prescription',
            ])
            ->whereIn('entryID', $this->selectedEntries)
            ->whereHas('member.emails')
            ->get();

        if ($entries->isEmpty()) {
            $this->addError(
                'selectedEntries',
                'Keine ausgewählten Mitglieder mit E-Mail-Adresse vorhanden.'
            );

            return;
        }

        $template = Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(TemplateRendererService::class);

        $sent = 0;

        foreach ($entries as $entry) {
            $member = $entry->member;
            $year = $entry->year;

            $email = $member->emails->first()?->email;

            if (! $email) {
                continue;
            }

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

            $amount = $entry->amount
                ?? $year->default_amount;

            $body = $renderer->membershipFeePrescription(
                $template,
                $entry
            );

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
                ]
            )->setPaper('a4', 'portrait');

            $tempDirectory = storage_path('app/temp');

            if (! is_dir($tempDirectory)) {
                mkdir($tempDirectory, 0775, true);
            }

            $tempPdf = $tempDirectory
                .'/mail_prescription_'
                .$entry->entryID
                .'.pdf';

            file_put_contents(
                $tempPdf,
                $pdf->output()
            );

            $letterheadPdf = storage_path(
                'app/templates/briefpapier.pdf'
            );

            $finalPdf = app(PdfLetterheadService::class)->apply(
                $tempPdf,
                $letterheadPdf
            );

            @unlink($tempPdf);

            $filename =
                'Mitgliedsbeitrag_'
                .$year->year
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
                $finalPdf
            );

            $filename =
                'Mitgliedsbeitrag_'
                .$year->year
                .'_'
                .$member->surname
                .'.pdf';

            $alreadySent = MembershipFeePrescription::query()
                ->where('entryID', $entry->entryID)
                ->where('type', 'prescription')
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->first();

            if ($alreadySent) {
                $this->resendEntryID = $entry->entryID;
                $this->showResendDialog = true;

                return;
            }

            $mail = new MembershipFeePrescriptionMail(
                memberName: trim(
                    $member->name.' '.$member->surname
                ),
                year: (int) $year->year,
                pdfContent: $finalPdf,
                pdfFilename: $filename
            );

            $rawMessage = null;

            $mail->withSymfonyMessage(
                function ($message) use (&$rawMessage) {
                    $rawMessage = $message->toString();
                }
            );

            Mail::to($email)->send($mail);

            if ($rawMessage) {
                try {
                    app(ImapSentMailService::class)->append(
                        $rawMessage
                    );
                } catch (\Throwable $imapException) {

                    \Log::warning(
                        'Beitragsvorschreibung wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                        [
                            'entryID' => $entry->entryID,
                            'memberID' => $member->memberID,
                            'error' => $imapException->getMessage(),
                        ]
                    );
                }
            }

            MembershipFeePrescription::create([
                'entryID' => $entry->entryID,
                'memberID' => $member->memberID,
                'yearID' => $year->yearID,

                'file_path' => $storagePath,
                'file_name' => $filename,

                'sent_to' => $email,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        session()->flash(
            'success',
            $sent.' Beitragsvorschreibung(en) wurden per E-Mail versendet.'
        );
    }

    public function sendSelectedReminders(): void
    {
        $this->showReminderConfirmDialog = false;

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.emails',
                'prescriptions',
                'year',
            ])
            ->whereIn('entryID', $this->selectedEntries)
            ->where('status', 'open')
            ->whereHas('member.emails')
            ->get();

        if ($entries->isEmpty()) {
            session()->flash(
                'error',
                'Keine offenen ausgewählten Beiträge mit E-Mail-Adresse gefunden.'
            );

            return;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($entries as $entry) {

            $hasPrescription = $entry->prescriptions
                ->where('type', 'prescription')
                ->whereNotNull('sent_at')
                ->isNotEmpty();

            if (! $hasPrescription) {
                $skipped++;

                continue;
            }

            if (! $entry->isReminderDue()) {
                $skipped++;

                continue;
            }

            $lastReminderLevel = $entry->prescriptions
                ->where('type', 'reminder')
                ->max('reminder_level');

            $nextReminderLevel = ($lastReminderLevel ?? 0) + 1;

            $this->sendSinglePrescription(
                $entry->entryID,
                'reminder',
                $nextReminderLevel
            );

            $sent++;
        }

        session()->flash(
            'success',
            $sent.' Erinnerung(en) wurden versendet.'
            .($skipped > 0
                ? ' '.$skipped.' Beitrag/Beiträge wurden übersprungen, da noch keine Vorschreibung versendet wurde.'
                : '')
        );
    }

    public function downloadSelectedReminders()
    {
        if (empty($this->selectedEntries)) {
            $this->addError(
                'selectedEntries',
                'Bitte mindestens einen Beitrag auswählen.'
            );

            return;
        }

        $ids = implode(',', array_map(
            'intval',
            $this->selectedEntries
        ));

        return redirect()->route(
            'membership-fees.reminders.selected',
            [
                'entries' => $ids,
            ]
        );
    }

    public function selectAllDueReminders(): void
    {
        if (! $this->yearId) {
            return;
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.emails',
                'prescriptions',
                'year',
            ])
            ->where('yearID', $this->yearId)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->whereHas('member.emails')
            ->get();

        $this->selectedEntries = $entries
            ->filter(fn ($entry) => $entry->isReminderDue())
            ->pluck('entryID')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function reminderSelectionSummary(): array
    {
        if (empty($this->selectedEntries)) {
            return [
                'selected' => 0,
                'sendable' => 0,
                'skipped' => 0,
            ];
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.emails',
                'prescriptions',
            ])
            ->whereIn('entryID', $this->selectedEntries)
            ->where('status', 'open')
            ->get();

        $sendable = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $hasEmail = $entry->member?->emails?->isNotEmpty();

            $hasPrescription = $entry->prescriptions
                ->where('type', 'prescription')
                ->whereNotNull('sent_at')
                ->isNotEmpty();

            if ($hasEmail && $hasPrescription) {
                $sendable++;
            } else {
                $skipped++;
            }
        }

        return [
            'selected' => count($this->selectedEntries),
            'sendable' => $sendable,
            'skipped' => $skipped,
        ];
    }

    public function requestEmailSend(int $entryID): void
    {
        $alreadySent = MembershipFeePrescription::query()
            ->where('entryID', $entryID)
            ->where('type', 'prescription')
            ->whereNotNull('sent_at')
            ->latest('sent_at')
            ->first();

        if ($alreadySent) {
            $this->resendEntryID = $entryID;
            $this->showResendDialog = true;

            return;
        }

        $this->sendSinglePrescription($entryID);
    }

    private function sendSinglePrescription(
        int $entryID,
        string $type = 'prescription',
        ?int $reminderLevel = null
    ): void {
        $entry = MembershipFeeEntry::query()
            ->with([
                'member.city',
                'member.emails',
                'year',
                'prescriptions',
            ])
            ->findOrFail($entryID);

        $member = $entry->member;
        $year = $entry->year;

        $email = $member->emails->first()?->email;

        if (! $email) {
            throw new \RuntimeException(
                'Für dieses Mitglied ist keine E-Mail-Adresse hinterlegt.'
            );
        }

        $templateKey = $type === 'reminder'
            ? 'membership_fee_reminder'
            : 'membership_fee_prescription';

        $template = Template::query()
            ->where('key', $templateKey)
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(TemplateRendererService::class);

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

        $amount = $entry->amount
            ?? $year->default_amount;

        $body = $type === 'reminder'
            ? $renderer->membershipFeeReminder(
                $template,
                $entry,
                $reminderLevel ?? 1
            )
            : $renderer->membershipFeePrescription(
                $template,
                $entry
            );

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
            ]
        )->setPaper('a4', 'portrait');

        $tempDirectory = storage_path('app/temp');

        if (! is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0775, true);
        }

        $tempPdf = $tempDirectory
            .'/mail_prescription_'
            .$entry->entryID
            .'.pdf';

        file_put_contents(
            $tempPdf,
            $pdf->output()
        );

        $letterheadPdf = storage_path(
            'app/templates/briefpapier.pdf'
        );

        $finalPdf = app(PdfLetterheadService::class)->apply(
            $tempPdf,
            $letterheadPdf
        );

        @unlink($tempPdf);

        $filename =
            'Mitgliedsbeitrag_'
            .$year->year
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
            $finalPdf
        );

        $mail = new MembershipFeePrescriptionMail(
            memberName: trim(
                $member->name.' '.$member->surname
            ),
            year: (int) $year->year,
            pdfContent: $finalPdf,
            pdfFilename: $filename,
            type: $type,
            reminderLevel: $reminderLevel
        );

        $rawMessage = null;

        $mail->withSymfonyMessage(
            function ($message) use (&$rawMessage) {
                $rawMessage = $message->toString();
            }
        );

        Mail::to($email)->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append(
                    $rawMessage
                );
            } catch (\Throwable $imapException) {

                \Log::warning(
                    'Beitragsvorschreibung/Erinnerung wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                    [
                        'entryID' => $entry->entryID,
                        'memberID' => $member->memberID,
                        'error' => $imapException->getMessage(),
                    ]
                );
            }
        }

        MembershipFeePrescription::create([
            'entryID' => $entry->entryID,
            'memberID' => $member->memberID,
            'yearID' => $year->yearID,

            'type' => $type,
            'reminder_level' => $reminderLevel,

            'file_path' => $storagePath,
            'file_name' => $filename,

            'sent_to' => $email,
            'sent_at' => now(),
        ]);
    }

    public function resendAsPrescription(): void
    {
        if (! $this->resendEntryID) {
            return;
        }

        $this->sendSinglePrescription(
            $this->resendEntryID,
            'prescription',
            null
        );

        $this->showResendDialog = false;
        $this->resendEntryID = null;

        session()->flash(
            'success',
            'Die Beitragsvorschreibung wurde erneut versendet.'
        );
    }

    public function sendAsReminder(): void
    {
        if (! $this->resendEntryID) {
            return;
        }

        $nextLevel = MembershipFeePrescription::query()
            ->where('entryID', $this->resendEntryID)
            ->where('type', 'reminder')
            ->max('reminder_level');

        $nextLevel = ($nextLevel ?? 0) + 1;

        $this->sendSinglePrescription(
            $this->resendEntryID,
            'reminder',
            $nextLevel
        );

        $this->showResendDialog = false;
        $this->resendEntryID = null;

        session()->flash(
            'success',
            'Die '
            .$nextLevel
            .'. Erinnerung wurde versendet.'
        );
    }

    public function clearSelection(): void
    {
        $this->selectedEntries = [];
    }

    public function updatedYearId(): void
    {
        $year = MembershipFeeYear::find($this->yearId);

        $this->defaultAmount = $year?->default_amount !== null
            ? number_format((float) $year->default_amount, 2, ',', '')
            : null;
    }

    public function createYear(): void
    {
        $validated = $this->validate([
            'newYear' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:tb_membership_fee_years,year'],
            'newYearName' => ['required', 'string', 'max:150'],
            'newYearAmount' => ['required'],
            'newYearDueDate' => ['nullable', 'date'],
        ]);

        $normalizedAmount = str_replace(',', '.', (string) $validated['newYearAmount']);

        if (! is_numeric($normalizedAmount)) {
            $this->addError('newYearAmount', 'Bitte einen gültigen Betrag eingeben.');

            return;
        }

        $year = DB::transaction(function () use ($validated, $normalizedAmount) {
            $year = MembershipFeeYear::create([
                'year' => $validated['newYear'],
                'name' => $validated['newYearName'],
                'default_amount' => round((float) $normalizedAmount, 2),
                'due_date' => $validated['newYearDueDate'] ?: null,
                'active' => true,
            ]);

            $members = Member::query()
                ->where('active', 1)
                ->get();

            foreach ($members as $member) {
                MembershipFeeEntry::create([
                    'yearID' => $year->yearID,
                    'memberID' => $member->memberID,
                    'amount' => $year->default_amount,
                    'status' => 'open',
                    'paid_at' => null,
                    'note' => null,
                ]);
            }

            return $year;
        });

        $this->yearId = $year->yearID;
        $this->defaultAmount = number_format((float) $year->default_amount, 2, ',', '');

        $this->reset([
            'showCreateYear',
            'newYear',
            'newYearName',
            'newYearAmount',
            'newYearDueDate',
        ]);

        session()->flash('success', 'Beitragsjahr wurde angelegt.');
    }

    public function toggleYearActive(): void
    {
        if (! $this->yearId) {
            return;
        }

        $year = MembershipFeeYear::findOrFail($this->yearId);

        $year->active = ! $year->active;
        $year->save();

        $this->firstReminderAfterDays =
            (int) $year->first_reminder_after_days;

        $this->reminderIntervalDays =
            (int) $year->reminder_interval_days;

        $this->maxReminders =
            (int) $year->max_reminders;

        session()->flash(
            'success',
            $year->active
                ? 'Beitragsjahr wurde wieder geöffnet.'
                : 'Beitragsjahr wurde abgeschlossen.'
        );
    }

    protected function selectedYearIsEditable(): bool
    {
        if (! $this->yearId) {
            return false;
        }

        return (bool) MembershipFeeYear::whereKey($this->yearId)
            ->value('active');
    }

    public function saveDefaultAmount(): void
    {
        if (! $this->selectedYearIsEditable()) {
            session()->flash(
                'error',
                'Dieses Beitragsjahr ist abgeschlossen und kann nicht mehr verändert werden.'
            );

            return;
        }

        if (! $this->yearId) {
            return;
        }

        $normalized = str_replace(',', '.', (string) $this->defaultAmount);

        if (! is_numeric($normalized)) {
            $this->addError(
                'defaultAmount',
                'Bitte einen gültigen Betrag eingeben.'
            );

            return;
        }

        $year = MembershipFeeYear::findOrFail($this->yearId);

        $year->default_amount = round((float) $normalized, 2);
        $year->save();

        session()->flash(
            'success',
            'Standardbeitrag wurde gespeichert.'
        );
    }

    public function applyDefaultAmount(): void
    {
        if (! $this->selectedYearIsEditable()) {
            session()->flash(
                'error',
                'Dieses Beitragsjahr ist abgeschlossen und kann nicht mehr verändert werden.'
            );

            return;
        }

        if (! $this->yearId) {
            return;
        }

        $year = MembershipFeeYear::findOrFail($this->yearId);

        if ($year->default_amount === null) {
            $this->addError(
                'defaultAmount',
                'Bitte zuerst einen Standardbetrag festlegen.'
            );

            return;
        }

        MembershipFeeEntry::query()
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->update([
                'amount' => $year->default_amount,
            ]);

        session()->flash(
            'success',
            'Standardbetrag wurde auf alle offenen Beiträge angewendet.'
        );
    }

    public function updateStatus(int $entryId, string $status): void
    {
        if (! $this->selectedYearIsEditable()) {
            session()->flash(
                'error',
                'Dieses Beitragsjahr ist abgeschlossen und kann nicht mehr verändert werden.'
            );

            return;
        }

        if (! in_array($status, ['open', 'paid', 'exempt'], true)) {
            return;
        }

        $entry = MembershipFeeEntry::query()
            ->whereKey($entryId)
            ->where('yearID', $this->yearId)
            ->firstOrFail();

        $entry->status = $status;

        if ($status === 'paid') {
            $entry->paid_at = $entry->paid_at ?? now();
        } else {
            $entry->paid_at = null;
        }

        $entry->save();
    }

    public function saveReminderSettings(): void
    {
        $year = MembershipFeeYear::findOrFail(
            $this->yearId
        );

        $this->validate([
            'firstReminderAfterDays' => [
                'required',
                'integer',
                'min:0',
            ],
            'reminderIntervalDays' => [
                'required',
                'integer',
                'min:1',
            ],
            'maxReminders' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],
        ]);

        $year->update([
            'first_reminder_after_days' => $this->firstReminderAfterDays,

            'reminder_interval_days' => $this->reminderIntervalDays,

            'max_reminders' => $this->maxReminders,
        ]);

        session()->flash(
            'success',
            'Erinnerungseinstellungen wurden gespeichert.'
        );
    }

    public function updateAmount(int $entryId, $amount): void
    {
        if (! $this->selectedYearIsEditable()) {
            session()->flash(
                'error',
                'Dieses Beitragsjahr ist abgeschlossen und kann nicht mehr verändert werden.'
            );

            return;
        }

        $entry = MembershipFeeEntry::findOrFail($entryId);

        if ($amount === '' || $amount === null) {
            $entry->amount = null;
            $entry->save();

            return;
        }

        $normalized = str_replace(',', '.', (string) $amount);

        if (! is_numeric($normalized)) {
            return;
        }

        $entry->amount = round((float) $normalized, 2);
        $entry->save();
    }

    public function render()
    {
        $years = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->get();

        $selectedYear = $this->yearId
            ? MembershipFeeYear::find($this->yearId)
            : null;

        $entries = collect();

        if ($selectedYear) {
            $entries = MembershipFeeEntry::query()
                ->whereHas('member', fn ($query) => $query->where('active', 1)
                )
                ->with([
                    'member',
                    'member.emails',
                    'year',
                    'prescriptions',
                ])
                ->where('yearID', $selectedYear->yearID)
                ->when(
                    $this->statusFilter !== 'all',
                    fn ($query) => $query->where('status', $this->statusFilter)
                )
                ->when(
                    $this->search,
                    function ($query) {
                        $query->whereHas('member', function ($query) {
                            $query
                                ->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('surname', 'like', '%'.$this->search.'%');
                        });
                    }
                )
                ->when(
                    $this->emailFilter === 'with_email',
                    fn ($query) => $query->whereHas('member.emails')
                )
                ->when(
                    $this->emailFilter === 'without_email',
                    fn ($query) => $query->whereDoesntHave('member.emails')
                )
                ->get()
                ->sortBy(fn ($entry) => ($entry->member?->surname ?? '')
                    .' '
                    .($entry->member?->name ?? '')
                )
                ->values();

            if ($this->reminderFilter === 'due') {
                $entries = $entries
                    ->filter(fn ($entry) => $entry->isReminderDue())
                    ->values();
            }

            if ($this->reminderFilter === 'sent') {
                $entries = $entries
                    ->filter(fn ($entry) => $entry->prescriptions
                        ->where('type', 'reminder')
                        ->whereNotNull('sent_at')
                        ->isNotEmpty()
                    )
                    ->values();
            }
        }

        $paidCount = $entries->where('status', 'paid')->count();
        $openCount = $entries->where('status', 'open')->count();

        $knownAmountTotal = $entries
            ->filter(fn ($entry) => $entry->amount !== null)
            ->sum(fn ($entry) => (float) $entry->amount);

        return view('livewire.membership-fees.index', [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'entries' => $entries,
            'paidCount' => $paidCount,
            'openCount' => $openCount,
            'knownAmountTotal' => $knownAmountTotal,
        ])->layout('layouts.app', [
            'title' => 'Mitgliedsbeiträge | VEMA',
            'heading' => 'Mitgliedsbeiträge',
        ]);
    }
}

<?php

namespace App\Livewire\MembershipFees;

use App\Mail\MembershipFeePrescriptionMail;
use App\Models\Member;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeePrescription;
use App\Models\MembershipFeeYear;
use App\Models\Template;
use App\Services\ActivityLogger;
use App\Services\ImapSentMailService;
use App\Services\MailQuota;
use App\Services\PdfLetterheadService;
use App\Services\TemplateRendererService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
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
                'prescriptions',
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

        // Vor dem ersten Versand: reicht das E-Mail-Kontingent für alle?
        app(MailQuota::class)->ensureAvailable($entries->count());

        $template = Template::query()
            ->where('key', 'membership_fee_prescription')
            ->where('active', true)
            ->firstOrFail();

        $renderer = app(TemplateRendererService::class);

        $sent = 0;
        $sentEntries = collect();

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

            $finalPdf = app(PdfLetterheadService::class)
                ->applyClubLetterhead($pdf->output());

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
                $this->logDispatch('fee.prescriptions_sent', $sentEntries);

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
            $sentEntries->push($entry);
        }

        $this->logDispatch('fee.prescriptions_sent', $sentEntries);

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

        app(MailQuota::class)->ensureAvailable($entries->count());

        $sent = 0;
        $skipped = 0;
        $sentEntries = collect();

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
            $sentEntries->push($entry);
        }

        $this->logDispatch('fee.reminders_sent', $sentEntries);

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

        $this->logDispatch('fee.prescriptions_sent', MembershipFeeEntry::query()->whereKey($entryID)->get());
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

        $finalPdf = app(PdfLetterheadService::class)
            ->applyClubLetterhead($pdf->output());

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

        $this->logDispatch('fee.prescriptions_sent', MembershipFeeEntry::query()->whereKey($this->resendEntryID)->get());

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

        $this->logDispatch('fee.reminders_sent', MembershipFeeEntry::query()->whereKey($this->resendEntryID)->get());

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

        $year->refresh();

        ActivityLogger::log(
            'fee.year_created',
            'Beitragsjahr '.$year->year.' „'.$year->display_name.'“ wurde mit '.$year->entries()->count().' Beiträgen angelegt.',
            'MembershipFeeYear',
            $year->yearID,
            ActivityLogger::created($this->yearSnapshot($year), self::YEAR_ACTIVITY_LABELS)
        );

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

        ActivityLogger::log(
            $year->active ? 'fee.year_reopened' : 'fee.year_closed',
            'Beitragsjahr '.$year->year.' wurde '.($year->active ? 'wieder geöffnet.' : 'abgeschlossen.'),
            'MembershipFeeYear',
            $year->yearID
        );

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
        $before = $this->yearSnapshot($year);

        $year->default_amount = round((float) $normalized, 2);
        $year->save();

        $changes = ActivityLogger::changes($before, $this->yearSnapshot($year->fresh()), self::YEAR_ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'fee.default_amount_updated',
                'Standardbetrag für das Beitragsjahr '.$year->year.' wurde geändert.',
                'MembershipFeeYear',
                $year->yearID,
                $changes
            );
        }

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

        $changedEntries = MembershipFeeEntry::query()
            ->with('member')
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->get()
            ->filter(fn ($entry) => $entry->amount !== $year->default_amount);

        MembershipFeeEntry::query()
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1)
            )
            ->update([
                'amount' => $year->default_amount,
            ]);

        if ($changedEntries->isNotEmpty()) {
            ActivityLogger::log(
                'fee.default_amount_applied',
                'Standardbetrag '.$this->formatAmount($year->default_amount).' wurde im Beitragsjahr '.$year->year.' auf '.$changedEntries->count().' offene Beiträge angewendet.',
                'MembershipFeeYear',
                $year->yearID,
                ActivityLogger::created(
                    ['amount' => $this->formatAmount($year->default_amount), 'members' => $this->memberNames($changedEntries)],
                    ['amount' => 'Betrag', 'members' => 'Mitglieder']
                )
            );
        }

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

        $before = $this->entrySnapshot($entry);

        $entry->status = $status;

        if ($status === 'paid') {
            $entry->paid_at = $entry->paid_at ?? now();
        } else {
            $entry->paid_at = null;
        }

        $entry->save();

        $this->logEntryUpdate($entry, $before, match ($status) {
            'paid' => 'wurde als bezahlt markiert.',
            'exempt' => 'wurde als befreit markiert.',
            default => 'wurde wieder auf offen gesetzt.',
        });
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

        $before = $year->only(array_keys(self::REMINDER_ACTIVITY_LABELS));

        $year->update([
            'first_reminder_after_days' => $this->firstReminderAfterDays,

            'reminder_interval_days' => $this->reminderIntervalDays,

            'max_reminders' => $this->maxReminders,
        ]);

        $changes = ActivityLogger::changes(
            $before,
            $year->fresh()->only(array_keys(self::REMINDER_ACTIVITY_LABELS)),
            self::REMINDER_ACTIVITY_LABELS
        );

        if ($changes !== []) {
            ActivityLogger::log(
                'fee.reminder_settings_updated',
                'Erinnerungseinstellungen für das Beitragsjahr '.$year->year.' wurden geändert.',
                'MembershipFeeYear',
                $year->yearID,
                $changes
            );
        }

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
        $before = $this->entrySnapshot($entry);

        if ($amount === '' || $amount === null) {
            $entry->amount = null;
            $entry->save();

            $this->logEntryUpdate($entry, $before, 'wurde geändert.');

            return;
        }

        $normalized = str_replace(',', '.', (string) $amount);

        if (! is_numeric($normalized)) {
            return;
        }

        $entry->amount = round((float) $normalized, 2);
        $entry->save();

        $this->logEntryUpdate($entry, $before, 'wurde geändert.');
    }

    /**
     * @var array<string, string>
     */
    private const YEAR_ACTIVITY_LABELS = [
        'year' => 'Jahr',
        'name' => 'Bezeichnung',
        'default_amount' => 'Standardbetrag',
        'due_date' => 'Fälligkeit',
    ];

    /**
     * @var array<string, string>
     */
    private const REMINDER_ACTIVITY_LABELS = [
        'first_reminder_after_days' => 'Erste Erinnerung nach Tagen',
        'reminder_interval_days' => 'Abstand zwischen Erinnerungen',
        'max_reminders' => 'Maximale Erinnerungen',
    ];

    /**
     * @var array<string, string>
     */
    private const ENTRY_ACTIVITY_LABELS = [
        'amount' => 'Betrag',
        'status' => 'Status',
        'paid_at' => 'Bezahlt am',
    ];

    /**
     * Texte der Protokolleinträge für Versand und Druck.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const DISPATCH_ACTIVITY_TEXTS = [
        'fee.prescriptions_sent' => ['Beitragsvorschreibung', 'Beitragsvorschreibungen', 'per E-Mail versendet'],
        'fee.reminders_sent' => ['Erinnerung', 'Erinnerungen', 'per E-Mail versendet'],
    ];

    /**
     * @return array{year: int, name: ?string, default_amount: ?string, due_date: mixed}
     */
    private function yearSnapshot(MembershipFeeYear $year): array
    {
        return [
            'year' => $year->year,
            'name' => $year->name,
            'default_amount' => $this->formatAmount($year->default_amount),
            'due_date' => $year->due_date,
        ];
    }

    /**
     * @return array{amount: ?string, status: string, paid_at: ?string}
     */
    private function entrySnapshot(MembershipFeeEntry $entry): array
    {
        return [
            'amount' => $this->formatAmount($entry->amount),
            'status' => match ($entry->status) {
                'paid' => 'Bezahlt',
                'exempt' => 'Befreit',
                'open' => 'Offen',
                default => (string) $entry->status,
            },
            'paid_at' => $entry->paid_at?->format('d.m.Y'),
        ];
    }

    /**
     * Ein Eintrag je Inline-Änderung; nichts, wenn sich nichts geändert hat.
     *
     * @param  array<string, mixed>  $before
     */
    private function logEntryUpdate(MembershipFeeEntry $entry, array $before, string $what): void
    {
        $entry = $entry->fresh(['member', 'year']);

        $changes = ActivityLogger::changes($before, $this->entrySnapshot($entry), self::ENTRY_ACTIVITY_LABELS);

        if ($changes === []) {
            return;
        }

        ActivityLogger::log(
            'fee.entry_updated',
            'Beitrag '.$entry->year?->year.' von „'.($entry->member?->full_name ?? 'Unbekannt').'“ '.$what,
            'MembershipFeeEntry',
            $entry->entryID,
            $changes
        );
    }

    /**
     * Ein zusammenfassender Eintrag je Versand, nicht einer je Mitglied.
     *
     * @param  Collection<int, MembershipFeeEntry>  $entries
     */
    private function logDispatch(string $action, Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        $entries = MembershipFeeEntry::query()
            ->with(['member', 'year'])
            ->whereKey($entries->pluck('entryID'))
            ->get();

        [$singular, $plural, $channel] = self::DISPATCH_ACTIVITY_TEXTS[$action];

        $years = $entries->pluck('year.year')->filter()->unique()->sort()->implode(', ');
        $yearIds = $entries->pluck('yearID')->unique();

        $description = $entries->count() === 1
            ? $singular.' '.$years.' wurde an „'.($entries->first()->member?->full_name ?? 'Unbekannt').'“ '.$channel.'.'
            : $plural.' '.$years.' wurden an '.$entries->count().' Mitglieder '.$channel.'.';

        ActivityLogger::log(
            $action,
            $description,
            $entries->count() === 1 ? 'MembershipFeeEntry' : ($yearIds->count() === 1 ? 'MembershipFeeYear' : null),
            $entries->count() === 1 ? $entries->first()->entryID : ($yearIds->count() === 1 ? $yearIds->first() : null),
            $entries->count() > 1
                ? ActivityLogger::created(['members' => $this->memberNames($entries)], ['members' => 'Mitglieder'])
                : null
        );
    }

    /**
     * @param  Collection<int, MembershipFeeEntry>  $entries
     * @return list<string>
     */
    private function memberNames(Collection $entries): array
    {
        return $entries
            ->map(fn ($entry) => $entry->member?->full_name)
            ->filter()
            ->values()
            ->all();
    }

    private function formatAmount(mixed $amount): ?string
    {
        return $amount === null
            ? null
            : number_format((float) $amount, 2, ',', '.').' €';
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

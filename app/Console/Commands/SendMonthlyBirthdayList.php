<?php

namespace App\Console\Commands;

use App\Mail\BirthdayListMail;
use App\Models\Member;
use App\Models\Setting;
use App\Services\BirthdayRecipientService;
use App\Services\ImapSentMailService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMonthlyBirthdayList extends Command
{
    protected $signature = 'birthdays:send-monthly-list';

    protected $description = 'Erzeugt die PDF-Geburtstagsliste des aktuellen Monats und verschickt sie an den Vorstand';

    public function __construct(
        private BirthdayRecipientService $recipientService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = now()->month;

        $members = Member::query()
            ->active()
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $month)
            ->orderByRaw('DAY(dateOfBirth)')
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(function (Member $member) {
                $age = $member->ageOn(now());

                return [
                    'member' => $member,
                    'age' => $age,
                    'isRound' => $member->isRoundBirthday($age),
                    'isHalfRound' => $member->isHalfRoundBirthday($age),
                ];
            });

        $monthName = now()->translatedFormat('F Y');

        $recipients = $this->recipientService->resolveEmails();

        if ($recipients->isEmpty()) {
            $this->warn(
                'Keine Empfänger in der Gruppe "'
                .Setting::current()->birthdayRecipientGroupName()
                .'" gefunden — es wurde keine Mail verschickt.'
            );

            return self::FAILURE;
        }

        $pdf = Pdf::loadView(
            'pdf.members-birthdays',
            [
                'members' => $members,
                'monthName' => $monthName,
            ]
        )->setPaper('a4', 'portrait');

        $pdfFileName = 'Geburtstagsliste_'
            .now()->format('m_Y')
            .'.pdf';

        $mail = new BirthdayListMail(
            monthName: $monthName,
            memberCount: $members->count(),
            pdfContent: $pdf->output(),
            pdfFileName: $pdfFileName
        );

        $rawMessage = null;

        $mail->withSymfonyMessage(
            function ($message) use (&$rawMessage) {
                $rawMessage = $message->toString();
            }
        );

        Mail::to($recipients->all())->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append(
                    $rawMessage
                );
            } catch (\Throwable $imapException) {

                \Log::warning(
                    'Geburtstagsliste wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                    [
                        'monthName' => $monthName,
                        'error' => $imapException->getMessage(),
                    ]
                );
            }
        }

        $this->info(
            'Geburtstagsliste '.$monthName
            .' an '.$recipients->count()
            .' Empfänger verschickt ('.$members->count().' Mitglieder).'
        );

        return self::SUCCESS;
    }
}

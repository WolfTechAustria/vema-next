<?php

namespace App\Console\Commands;

use App\Mail\BirthdayReminderMail;
use App\Models\BirthdayReminderSent;
use App\Models\Member;
use App\Models\Setting;
use App\Services\BirthdayRecipientService;
use App\Services\ImapSentMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendBirthdayReminders extends Command
{
    protected $signature = 'birthdays:send-reminders';

    protected $description = 'Prüft aktive Mitglieder auf runde/halbrunde Geburtstage und informiert den Vorstand per E-Mail';

    public function __construct(
        private BirthdayRecipientService $recipientService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now();

        $members = Member::query()
            ->active()
            ->birthdayOn($today)
            ->get();

        if ($members->isEmpty()) {
            $this->info('Keine Geburtstage aktiver Mitglieder heute.');

            return self::SUCCESS;
        }

        $recipients = $this->recipientService->resolveEmails();

        if ($recipients->isEmpty()) {
            $this->warn(
                'Keine Empfänger in der Gruppe "'
                .Setting::current()->birthdayRecipientGroupName()
                .'" gefunden — es wurde keine Mail verschickt.'
            );

            return self::FAILURE;
        }

        foreach ($members as $member) {
            $age = $member->ageOn($today);

            if (! $member->isRoundOrHalfRoundBirthday($age)) {
                continue;
            }

            /*
             * Verhindert Doppelversand, falls der Command mehrfach
             * am selben Tag läuft oder erneut angestoßen wird.
             */
            $alreadySent = BirthdayReminderSent::query()
                ->where('memberID', $member->memberID)
                ->where('age', $age)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $mail = new BirthdayReminderMail(
                member: $member,
                age: $age,
                isRound: $member->isRoundBirthday($age)
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
                        'Geburtstags-Erinnerung wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                        [
                            'memberID' => $member->memberID,
                            'error' => $imapException->getMessage(),
                        ]
                    );
                }
            }

            BirthdayReminderSent::query()->create([
                'memberID' => $member->memberID,
                'age' => $age,
                'birthday_date' => $today->toDateString(),
                'sent_at' => $today,
            ]);

            $this->info(
                'Reminder verschickt für '.$member->full_name
                .' ('.$age.' Jahre).'
            );
        }

        return self::SUCCESS;
    }
}

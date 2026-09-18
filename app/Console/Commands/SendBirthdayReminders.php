<?php

namespace App\Console\Commands;

use App\Mail\BirthdayReminderMail;
use App\Models\Member;
use App\Models\BirthdayReminderSent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Services\BirthdayRecipientService;

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
                . config('birthday.recipient_group')
                . '" gefunden — es wurde keine Mail verschickt.'
            );

            return self::FAILURE;
        }

        foreach ($members as $member) {
            $age = $member->ageOn($today);

            if (!$member->isRoundOrHalfRoundBirthday($age)) {
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

            Mail::to($recipients->all())->send($mail);

            BirthdayReminderSent::query()->create([
                'memberID' => $member->memberID,
                'age' => $age,
                'birthday_date' => $today->toDateString(),
                'sent_at' => $today,
            ]);

            $this->info(
                'Reminder verschickt für ' . $member->full_name
                . ' (' . $age . ' Jahre).'
            );
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\DutyReminderMail;
use App\Models\DutyPlanAssignment;
use App\Models\DutyReminderSent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDutyReminders extends Command
{
    protected $signature = 'duty:send-reminders';

    protected $description = 'Verschickt 2 Tage vor dem Dienst eine Erinnerungs-Mail an eingeteilte Mitglieder (sofern aktiviert)';

    public function handle(): int
    {
        $targetDate = now()->addDays(2)->toDateString();

        $assignments = DutyPlanAssignment::query()
            ->whereNotNull('memberID')
            ->whereHas('event', fn ($query) => $query->where('duty_date', $targetDate))
            ->with(['event', 'member.emails', 'member.dutySettings'])
            ->get();

        if ($assignments->isEmpty()) {
            $this->info('Keine Dienste in 2 Tagen gefunden.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($assignments as $assignment) {
            $member = $assignment->member;

            if (!$member || !$member->active) {
                continue;
            }

            if (!$member->dutyReminderEnabled()) {
                continue;
            }

            $alreadySent = DutyReminderSent::query()
                ->where('assignmentID', $assignment->assignmentID)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $email = $member->emails->first()?->email;

            if (!$email) {
                $this->warn(
                    'Keine E-Mail-Adresse für ' . $member->full_name . ' hinterlegt — übersprungen.'
                );

                continue;
            }

            Mail::to($email)->send(new DutyReminderMail($assignment));

            DutyReminderSent::query()->create([
                'assignmentID' => $assignment->assignmentID,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        $this->info($sent . ' Dienst-Erinnerung(en) verschickt.');

        return self::SUCCESS;
    }
}

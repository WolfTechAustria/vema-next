<?php

namespace App\Services;

use App\Enums\DutySwapStatus;
use App\Enums\DutySwapType;
use App\Enums\PushCategory;
use App\Mail\DutySwapAdminMail;
use App\Mail\DutySwapAnsweredMail;
use App\Mail\DutySwapRequestedMail;
use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanVolunteer;
use App\Models\DutySwapRequest;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tausch und Abgabe von Diensten zwischen Mitgliedern: Anfrage, Annahme,
 * optionale Freigabe durch den Verein und Umsetzung im Dienstplan.
 */
class DutySwaps
{
    // ---------------------------------------------------------------
    // Einstellungen
    // ---------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) (Setting::current()->duty_swaps_enabled ?? true);
    }

    public function requiresApproval(): bool
    {
        return (bool) Setting::current()->duty_swap_requires_approval;
    }

    public function deadlineHours(): int
    {
        return max(0, (int) Setting::current()->duty_swap_deadline_hours);
    }

    /**
     * Beginn des Dienstes (ohne Uhrzeit: Tagesbeginn).
     */
    public function startOf(DutyPlanEvent $event): CarbonInterface
    {
        $start = CarbonImmutable::parse($event->duty_date->toDateString());

        if ($event->start_time) {
            $start = CarbonImmutable::parse($event->duty_date->toDateString().' '.$event->start_time);
        }

        return $start;
    }

    /**
     * Bis zu diesem Zeitpunkt darf der Dienst getauscht werden.
     */
    public function deadlineFor(DutyPlanEvent $event): CarbonInterface
    {
        return $this->startOf($event)->subHours($this->deadlineHours());
    }

    public function isBeforeDeadline(DutyPlanEvent $event): bool
    {
        return now()->lt($this->deadlineFor($event));
    }

    /**
     * Empfänger der Vereins-Benachrichtigungen: eingestellte Adressen, sonst
     * alle Admins.
     *
     * @return list<string>
     */
    public function adminRecipients(): array
    {
        $emails = array_values(array_filter(Setting::current()->duty_swap_notify_emails ?? []));

        if ($emails !== []) {
            return $emails;
        }

        return User::role('admin')
            ->where('enabled', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------
    // Auswahl
    // ---------------------------------------------------------------

    /**
     * Ist das Mitglied (aktiv) als Helfer im Dienstplan erfasst?
     */
    public function isPlanMember(Member $member, int $planID): bool
    {
        return (bool) $member->active && DutyPlanVolunteer::query()
            ->where('planID', $planID)
            ->where('memberID', $member->memberID)
            ->where('active', 1)
            ->exists();
    }

    /**
     * Mitglieder desselben Dienstplans, an die eine Anfrage gehen kann.
     *
     * @return Collection<int, Member>
     */
    public function candidates(Member $requester, DutyPlanAssignment $assignment): Collection
    {
        return DutyPlanVolunteer::query()
            ->with('member')
            ->where('planID', $assignment->event->planID)
            ->where('active', 1)
            ->whereNotNull('memberID')
            ->where('memberID', '!=', $requester->memberID)
            ->get()
            ->pluck('member')
            ->filter(fn (?Member $member) => $member !== null && $member->active)
            ->sortBy(fn (Member $member) => $member->surname.' '.$member->name)
            ->values();
    }

    /**
     * Kommende Dienste eines Mitglieds im selben Dienstplan, die sich gegen
     * den angebotenen Dienst tauschen lassen.
     *
     * @return Collection<int, DutyPlanAssignment>
     */
    public function targetAssignments(Member $member, DutyPlanAssignment $offered): Collection
    {
        return DutyPlanAssignment::query()
            ->with('event')
            ->where('memberID', $member->memberID)
            ->whereHas('event', fn ($query) => $query
                ->where('planID', $offered->event->planID)
                ->where('eventID', '!=', $offered->eventID)
                ->whereDate('duty_date', '>=', today()))
            ->get()
            ->filter(fn (DutyPlanAssignment $assignment) => $this->isBeforeDeadline($assignment->event)
                && ! $this->hasOpenRequest($assignment))
            ->sortBy(fn (DutyPlanAssignment $assignment) => $this->startOf($assignment->event))
            ->values();
    }

    public function hasOpenRequest(DutyPlanAssignment $assignment): bool
    {
        return DutySwapRequest::query()
            ->open()
            ->where(fn ($query) => $query
                ->where('assignmentID', $assignment->assignmentID)
                ->orWhere('targetAssignmentID', $assignment->assignmentID))
            ->exists();
    }

    /**
     * Darf das Mitglied diesen Dienst zum Tausch anbieten?
     */
    public function canOffer(Member $member, DutyPlanAssignment $assignment): bool
    {
        return $this->isEnabled()
            && $assignment->memberID === $member->memberID
            && $assignment->event !== null
            && $this->isBeforeDeadline($assignment->event)
            && ! $this->hasOpenRequest($assignment);
    }

    // ---------------------------------------------------------------
    // Konflikte
    // ---------------------------------------------------------------

    /**
     * Hinweise, wenn das Mitglied den Dienst übernimmt (Tausch bleibt
     * erlaubt).
     *
     * @return list<string>
     */
    public function warningsFor(Member $member, DutyPlanEvent $event, ?int $givenAwayAssignmentID = null): array
    {
        $warnings = [];
        $date = $event->duty_date->toDateString();

        $absent = DutyPlanAbsence::query()
            ->where('memberID', $member->memberID)
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->exists();

        if ($absent) {
            $warnings[] = $member->full_name.' ist am '.$event->duty_date->format('d.m.Y').' als abwesend eingetragen.';
        }

        $otherDuty = DutyPlanAssignment::query()
            ->where('memberID', $member->memberID)
            ->where('eventID', '!=', $event->eventID)
            ->when($givenAwayAssignmentID, fn ($query) => $query->where('assignmentID', '!=', $givenAwayAssignmentID))
            ->whereHas('event', fn ($query) => $query->whereDate('duty_date', $date))
            ->exists();

        if ($otherDuty) {
            $warnings[] = $member->full_name.' hat am '.$event->duty_date->format('d.m.Y').' bereits einen anderen Dienst.';
        }

        $requiredSkillID = $event->role?->requiredSkillID;

        if ($requiredSkillID && ! $member->skills()->where('tb_skill_members.skillID', $requiredSkillID)->exists()) {
            $warnings[] = $member->full_name.' hat nicht die für „'.$event->duty_name.'“ erforderliche Fähigkeit.';
        }

        return $warnings;
    }

    /**
     * Hinweise für alle Beteiligten einer Anfrage aus Sicht des übernehmenden
     * Mitglieds (und beim Tausch auch des Anfragenden).
     *
     * @return list<string>
     */
    public function warningsForRequest(DutySwapRequest $swap, Member $acceptor): array
    {
        $warnings = $this->warningsFor($acceptor, $swap->assignment->event, $swap->targetAssignmentID);

        if ($swap->isSwap() && $swap->targetAssignment?->event) {
            $warnings = [
                ...$warnings,
                ...$this->warningsFor($swap->requester, $swap->targetAssignment->event, $swap->assignmentID),
            ];
        }

        return $warnings;
    }

    /**
     * Ist das Mitglied bereits im selben Termin eingeteilt? Das schließt die
     * Übernahme aus.
     */
    private function alreadyOnEvent(int $memberID, int $eventID): bool
    {
        return DutyPlanAssignment::query()
            ->where('eventID', $eventID)
            ->where('memberID', $memberID)
            ->exists();
    }

    // ---------------------------------------------------------------
    // Ablauf
    // ---------------------------------------------------------------

    /**
     * Neue Anfrage. Beim Tausch ist "$targetAssignment" der gewünschte Dienst
     * des anderen Mitglieds; bei der Abgabe ohne "$target" geht sie an alle
     * im Dienstplan.
     */
    public function request(
        Member $requester,
        DutyPlanAssignment $assignment,
        DutySwapType $type,
        ?Member $target = null,
        ?DutyPlanAssignment $targetAssignment = null,
        ?string $message = null,
    ): DutySwapRequest {
        $assignment->loadMissing('event');

        if (! $this->canOffer($requester, $assignment)) {
            throw ValidationException::withMessages(['swap' => 'Dieser Dienst kann nicht (mehr) getauscht werden.']);
        }

        $planID = $assignment->event->planID;

        if ($target !== null && ($target->memberID === $requester->memberID || ! $this->isPlanMember($target, $planID))) {
            throw ValidationException::withMessages(['swap' => 'Anfragen sind nur an Mitglieder desselben Dienstplans möglich.']);
        }

        if ($type === DutySwapType::Swap) {
            $valid = $target !== null
                && $targetAssignment !== null
                && $this->targetAssignments($target, $assignment)->contains('assignmentID', $targetAssignment->assignmentID);

            if (! $valid) {
                throw ValidationException::withMessages(['swap' => 'Bitte einen Dienst des anderen Mitglieds wählen.']);
            }

            if ($this->alreadyOnEvent($requester->memberID, $targetAssignment->eventID)) {
                throw ValidationException::withMessages(['swap' => 'Du bist bei diesem Termin bereits eingeteilt.']);
            }
        }

        if ($target !== null && $this->alreadyOnEvent($target->memberID, $assignment->eventID)) {
            throw ValidationException::withMessages(['swap' => $target->full_name.' ist bei diesem Termin bereits eingeteilt.']);
        }

        $swap = DutySwapRequest::create([
            'type' => $type,
            'requesterID' => $requester->memberID,
            'assignmentID' => $assignment->assignmentID,
            'targetMemberID' => $target?->memberID,
            'targetAssignmentID' => $type === DutySwapType::Swap ? $targetAssignment?->assignmentID : null,
            'status' => DutySwapStatus::Pending,
            'message' => filled($message) ? mb_substr(trim($message), 0, 500) : null,
        ]);

        ActivityLogger::log(
            'portal.duty_swap_requested',
            'Mitglied "'.$requester->full_name.'" (#'.$requester->memberID.') hat '
                .($type === DutySwapType::Swap ? 'einen Tausch' : 'die Abgabe').' des Dienstes '
                .$this->describe($assignment->event).' angefragt'
                .($target ? ' (an '.$target->full_name.').' : ' (an alle im Dienstplan).'),
            'DutySwapRequest',
            $swap->swapID
        );

        $recipients = $target !== null
            ? collect([$target])
            : $this->candidates($requester, $assignment);

        $this->push($recipients, $type === DutySwapType::Swap ? 'Tauschanfrage' : 'Dienst zur Übernahme',
            $requester->full_name.($type === DutySwapType::Swap ? ' möchte Dienste tauschen: ' : ' sucht Ersatz für ')
                .$this->describe($assignment->event).'.', $swap);

        foreach ($recipients as $recipient) {
            $this->mailMember($recipient, new DutySwapRequestedMail($swap, $recipient));
        }

        return $swap;
    }

    /**
     * Das angefragte Mitglied (bzw. bei „an alle“ das erste) nimmt an.
     */
    public function accept(DutySwapRequest $swap, Member $acceptor): DutySwapRequest
    {
        $swap = $this->inTransaction(function () use ($swap, $acceptor) {
            $swap = DutySwapRequest::query()->lockForUpdate()->findOrFail($swap->swapID);

            if ($swap->status !== DutySwapStatus::Pending || ! $this->canRespond($swap, $acceptor)) {
                return [null, 'Diese Anfrage ist nicht mehr offen.', $swap];
            }

            if ($problem = $this->problemWith($swap, $acceptor, checkDeadline: true)) {
                return [...$problem, $swap];
            }

            $swap->update([
                'acceptedByID' => $acceptor->memberID,
                'responded_at' => now(),
                'status' => $this->requiresApproval() ? DutySwapStatus::AwaitingApproval : DutySwapStatus::Pending,
            ]);

            if (! $this->requiresApproval()) {
                $this->perform($swap);
            }

            return $swap->fresh();
        });

        ActivityLogger::log(
            'portal.duty_swap_accepted',
            'Mitglied "'.$acceptor->full_name.'" (#'.$acceptor->memberID.') hat die Anfrage zu '
                .$this->describe($swap->assignment->event).' angenommen.',
            'DutySwapRequest',
            $swap->swapID
        );

        $this->mailMember($swap->requester, new DutySwapAnsweredMail($swap));
        $this->mailAdmins(new DutySwapAdminMail($swap));
        $this->pushAnswer($swap, $swap->requester);

        if ($swap->status === DutySwapStatus::AwaitingApproval) {
            app(PushNotifier::class)->toAdmins(
                PushCategory::Administration,
                'Diensttausch wartet auf Freigabe',
                $swap->requester?->full_name.' ↔ '.$swap->acceptedBy?->full_name.': '.$this->describe($swap->assignment->event),
                route('duty-plan.swaps'),
                'duty-swap-'.$swap->swapID,
            );
        }

        return $swap;
    }

    public function decline(DutySwapRequest $swap, Member $member): void
    {
        if ($swap->status !== DutySwapStatus::Pending || $swap->targetMemberID !== $member->memberID) {
            abort(404);
        }

        $swap->update(['status' => DutySwapStatus::Declined, 'responded_at' => now(), 'acceptedByID' => null]);

        ActivityLogger::log(
            'portal.duty_swap_declined',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat die Anfrage zu '
                .$this->describe($swap->assignment->event).' abgelehnt.',
            'DutySwapRequest',
            $swap->swapID
        );

        $this->mailMember($swap->requester, new DutySwapAnsweredMail($swap));
        $this->pushAnswer($swap, $swap->requester);
    }

    public function cancel(DutySwapRequest $swap, Member $member): void
    {
        if (! $swap->status->isOpen() || $swap->requesterID !== $member->memberID) {
            abort(404);
        }

        $swap->update(['status' => DutySwapStatus::Cancelled]);

        ActivityLogger::log(
            'portal.duty_swap_cancelled',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') hat die Anfrage zu '
                .$this->describe($swap->assignment->event).' zurückgezogen.',
            'DutySwapRequest',
            $swap->swapID
        );
    }

    /**
     * Freigabe durch den Verein.
     */
    public function approve(DutySwapRequest $swap, User $user): DutySwapRequest
    {
        $swap = $this->inTransaction(function () use ($swap, $user) {
            $swap = DutySwapRequest::query()->lockForUpdate()->findOrFail($swap->swapID);

            if ($swap->status !== DutySwapStatus::AwaitingApproval) {
                return [null, 'Diese Anfrage wartet nicht auf eine Freigabe.', $swap];
            }

            if ($problem = $this->problemWith($swap, $swap->acceptedBy, checkDeadline: false)) {
                return [...$problem, $swap];
            }

            $swap->update(['decidedByUserID' => $user->id]);
            $this->perform($swap);

            return $swap->fresh();
        });

        $this->mailMember($swap->requester, new DutySwapAnsweredMail($swap));
        $this->mailMember($swap->acceptedBy, new DutySwapAnsweredMail($swap, forAcceptor: true));
        $this->pushAnswer($swap, $swap->requester, $swap->acceptedBy);

        return $swap;
    }

    public function reject(DutySwapRequest $swap, User $user): void
    {
        if ($swap->status !== DutySwapStatus::AwaitingApproval) {
            throw ValidationException::withMessages(['swap' => 'Diese Anfrage wartet nicht auf eine Freigabe.']);
        }

        $swap->update(['status' => DutySwapStatus::Rejected, 'decidedByUserID' => $user->id]);

        ActivityLogger::log(
            'duty.swap_rejected',
            'Tausch zu '.$this->describe($swap->assignment->event).' wurde nicht freigegeben.',
            'DutySwapRequest',
            $swap->swapID
        );

        $this->mailMember($swap->requester, new DutySwapAnsweredMail($swap));
        $this->mailMember($swap->acceptedBy, new DutySwapAnsweredMail($swap, forAcceptor: true));
        $this->pushAnswer($swap, $swap->requester, $swap->acceptedBy);
    }

    /**
     * Darf das Mitglied auf die Anfrage antworten?
     */
    public function canRespond(DutySwapRequest $swap, Member $member): bool
    {
        if ($swap->requesterID === $member->memberID) {
            return false;
        }

        if ($swap->targetMemberID !== null) {
            return $swap->targetMemberID === $member->memberID;
        }

        $planID = $swap->assignment?->event?->planID;

        return $planID !== null && $this->isPlanMember($member, $planID);
    }

    /**
     * Offene Anfragen, deren Frist abgelaufen ist, als abgelaufen markieren.
     */
    public function expireStale(): void
    {
        DutySwapRequest::query()
            ->where('status', DutySwapStatus::Pending)
            ->with(['assignment.event', 'targetAssignment.event'])
            ->get()
            ->filter(fn (DutySwapRequest $swap) => $this->isPastDeadline($swap))
            ->each(fn (DutySwapRequest $swap) => $swap->update(['status' => DutySwapStatus::Expired]));
    }

    /**
     * „Bar am Fr, 20.11.2026, 18:00 Uhr“
     */
    public function describe(?DutyPlanEvent $event): string
    {
        if (! $event) {
            return 'Dienst';
        }

        $text = '„'.$event->duty_name.'“ am '.$event->duty_date->translatedFormat('D, d.m.Y');

        if ($event->start_time) {
            $text .= ', '.substr((string) $event->start_time, 0, 5).' Uhr';
        }

        return $text;
    }

    private function isPastDeadline(DutySwapRequest $swap): bool
    {
        $events = array_filter([$swap->assignment?->event, $swap->targetAssignment?->event]);

        return collect($events)->contains(fn (DutyPlanEvent $event) => ! $this->isBeforeDeadline($event));
    }

    /**
     * Prüft, ob die Dienste noch wie angefragt verteilt sind und die
     * Übernahme möglich ist.
     *
     * @return array{0: ?DutySwapStatus, 1: string}|null neuer Status der Anfrage (falls sie damit endet) und Meldung
     */
    private function problemWith(DutySwapRequest $swap, ?Member $acceptor, bool $checkDeadline): ?array
    {
        $swap->load(['assignment.event', 'targetAssignment.event']);

        $stillAssigned = $acceptor !== null
            && $swap->assignment?->memberID === $swap->requesterID
            && (! $swap->isSwap() || $swap->targetAssignment?->memberID === $acceptor->memberID);

        if (! $stillAssigned) {
            return [DutySwapStatus::Obsolete, 'Der Dienstplan wurde inzwischen geändert – die Anfrage ist hinfällig.'];
        }

        if ($checkDeadline && $this->isPastDeadline($swap)) {
            return [DutySwapStatus::Expired, 'Die Frist für diesen Tausch ist abgelaufen.'];
        }

        if ($this->alreadyOnEvent($acceptor->memberID, $swap->assignment->eventID)
            || ($swap->isSwap() && $this->alreadyOnEvent($swap->requesterID, $swap->targetAssignment->eventID))) {
            return [null, 'Eine der Personen ist bei diesem Termin bereits eingeteilt.'];
        }

        return null;
    }

    /**
     * Führt den Schritt gesperrt in einer Transaktion aus. Liefert der Schritt
     * ein Problem [Status, Meldung, Anfrage] statt der Anfrage, wird der
     * Status außerhalb der (dann leeren) Transaktion gesetzt und die Meldung
     * als Validierungsfehler ausgegeben.
     *
     * @param  callable(): (DutySwapRequest|array{0: ?DutySwapStatus, 1: string, 2: DutySwapRequest})  $step
     */
    private function inTransaction(callable $step): DutySwapRequest
    {
        $result = DB::transaction($step);

        if ($result instanceof DutySwapRequest) {
            return $result;
        }

        [$status, $message, $swap] = $result;

        if ($status !== null) {
            $swap->update(['status' => $status]);
        }

        throw ValidationException::withMessages(['swap' => $message]);
    }

    /**
     * Setzt den Tausch im Dienstplan um.
     */
    private function perform(DutySwapRequest $swap): void
    {
        $assignment = DutyPlanAssignment::query()->lockForUpdate()->findOrFail($swap->assignmentID);
        $acceptorID = $swap->acceptedByID;

        if ($swap->isSwap()) {
            $targetAssignment = DutyPlanAssignment::query()->lockForUpdate()->findOrFail($swap->targetAssignmentID);
            $targetAssignment->update(['memberID' => $swap->requesterID]);
        }

        $assignment->update(['memberID' => $acceptorID]);

        $swap->update(['status' => DutySwapStatus::Completed, 'completed_at' => now()]);

        DutySwapRequest::query()
            ->open()
            ->where('swapID', '!=', $swap->swapID)
            ->where(fn ($query) => $query
                ->whereIn('assignmentID', array_filter([$swap->assignmentID, $swap->targetAssignmentID]))
                ->orWhereIn('targetAssignmentID', array_filter([$swap->assignmentID, $swap->targetAssignmentID])))
            ->update(['status' => DutySwapStatus::Obsolete->value]);

        $swap->load(['requester', 'acceptedBy', 'assignment.event', 'targetAssignment.event']);

        ActivityLogger::log(
            'duty.swap_completed',
            ($swap->isSwap() ? 'Dienste getauscht: ' : 'Dienst übergeben: ')
                .$this->describe($swap->assignment->event).' von '.$swap->requester?->full_name
                .' an '.$swap->acceptedBy?->full_name
                .($swap->isSwap() ? ', im Gegenzug '.$this->describe($swap->targetAssignment?->event) : '').'.',
            'DutyPlanEvent',
            $swap->assignment->eventID
        );
    }

    // ---------------------------------------------------------------
    // Versand
    // ---------------------------------------------------------------

    /**
     * @param  iterable<Member>  $members
     */
    private function push(iterable $members, string $title, string $body, DutySwapRequest $swap): void
    {
        app(PushNotifier::class)->toMembers($members, PushCategory::Duties, $title, $body, route('member.duties'), 'duty-swap-'.$swap->swapID);
    }

    /**
     * Rückmeldung zum Stand der Anfrage an die Beteiligten.
     */
    private function pushAnswer(DutySwapRequest $swap, ?Member ...$members): void
    {
        $event = $this->describe($swap->assignment?->event);

        $body = match ($swap->status) {
            DutySwapStatus::Completed => 'Erledigt – der Dienstplan wurde angepasst ('.$event.').',
            DutySwapStatus::AwaitingApproval => $swap->acceptedBy?->full_name.' hat angenommen. Der Tausch wartet noch auf die Freigabe ('.$event.').',
            DutySwapStatus::Declined => $swap->targetMember?->full_name.' hat abgelehnt ('.$event.').',
            DutySwapStatus::Rejected => 'Der Verein hat den Tausch nicht freigegeben ('.$event.').',
            default => null,
        };

        if ($body !== null) {
            $this->push(array_filter($members), 'Diensttausch', $body, $swap);
        }
    }

    private function mailMember(?Member $member, Mailable $mail): void
    {
        $email = $member?->emails()->value('email');

        if ($email) {
            $this->send($email, $mail);
        }
    }

    private function mailAdmins(Mailable $mail): void
    {
        $recipients = $this->adminRecipients();

        if ($recipients !== []) {
            $this->send($recipients, $mail);
        }
    }

    /**
     * @param  string|list<string>  $to
     */
    private function send(string|array $to, Mailable $mail): void
    {
        $rawMessage = null;
        $mail->withSymfonyMessage(function ($message) use (&$rawMessage) {
            $rawMessage = $message->toString();
        });

        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $exception) {
            Log::warning('E-Mail zum Diensttausch konnte nicht versendet werden.', [
                'mail' => $mail::class,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $exception) {
                Log::warning('E-Mail zum Diensttausch konnte nicht im Gesendet-Ordner abgelegt werden.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}

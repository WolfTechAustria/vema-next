<?php

namespace App\Services;

use App\Enums\MemberApplicationStatus;
use App\Enums\MembershipKind;
use App\Mail\MemberApplicationConfirmationMail;
use App\Mail\MemberApplicationReceivedMail;
use App\Mail\MemberApplicationRejectedMail;
use App\Mail\MemberApplicationVerifyMail;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberEmail;
use App\Models\MemberFile;
use App\Models\MemberPhone;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ablauf der Online-Mitgliedsanmeldung: Einreichen, E-Mail-Bestätigung,
 * Benachrichtigung, Freigabe (legt das Mitglied an) und Ablehnung.
 */
class MemberApplications
{
    /**
     * Gültigkeit des Bestätigungslinks in Stunden.
     */
    public const VERIFY_HOURS = 72;

    /**
     * Unbestätigte Anmeldungen werden nach so vielen Tagen gelöscht.
     */
    public const PRUNE_UNVERIFIED_AFTER_DAYS = 7;

    public function __construct(
        private MemberApplicationSettings $settings,
        private MemberFields $memberFields,
        private MemberApplicationPdf $pdf,
    ) {}

    /**
     * Speichert eine neue Anmeldung. Mit E-Mail-Bestätigung bleibt sie bis
     * zum Klick auf den Link unbestätigt, sonst ist sie sofort offen.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function submit(array $attributes, ?string $signaturePng = null): MemberApplication
    {
        $needsVerification = $this->settings->usesEmailVerification();
        $token = $needsVerification ? Str::random(48) : null;

        $application = MemberApplication::create($attributes + [
            'applied_on' => now(config('app.club_timezone'))->toDateString(),
            'status' => $needsVerification ? MemberApplicationStatus::Unverified : MemberApplicationStatus::Pending,
            'verification_token_hash' => $token ? hash('sha256', $token) : null,
            'verified_at' => $needsVerification ? null : now(),
            'ip' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);

        if ($signaturePng !== null) {
            $path = 'applications/'.$application->applicationID.'/signature.png';
            Storage::disk('local')->put($path, $signaturePng);
            $application->update(['signature_path' => $path]);
        }

        if ($token !== null) {
            $this->send($application->email, new MemberApplicationVerifyMail($application, $token));
        } else {
            $this->announce($application);
        }

        return $application;
    }

    /**
     * Bestätigt die E-Mail-Adresse über den Link; null bei ungültigem oder
     * abgelaufenem Link.
     */
    public function verify(string $token): ?MemberApplication
    {
        $application = $this->findUnverified($token);

        if ($application === null) {
            return null;
        }

        $application->update([
            'status' => MemberApplicationStatus::Pending,
            'verified_at' => now(),
            'verification_token_hash' => null,
        ]);

        $this->announce($application);

        return $application;
    }

    public function findUnverified(string $token): ?MemberApplication
    {
        return MemberApplication::query()
            ->where('status', MemberApplicationStatus::Unverified)
            ->where('verification_token_hash', hash('sha256', $token))
            ->where('created_at', '>=', now()->subHours(self::VERIFY_HOURS))
            ->first();
    }

    /**
     * Legt aus der Anmeldung ein aktives Mitglied an und legt die
     * Beitrittserklärung als PDF in seinen Dokumenten ab.
     *
     * @throws ValidationException
     */
    public function approve(MemberApplication $application, bool $sendWelcomeMail = true): Member
    {
        if (! $application->isOpen()) {
            throw ValidationException::withMessages(['approve' => 'Diese Anmeldung ist nicht mehr offen.']);
        }

        if (blank($application->gender)) {
            throw ValidationException::withMessages(['gender' => 'Bitte vor der Freigabe die Anrede ergänzen.']);
        }

        $entitlements = app(PlanEntitlements::class);

        if (! $entitlements->canAddActiveMember()) {
            throw ValidationException::withMessages(['approve' => $entitlements->memberLimitMessage()]);
        }

        $member = DB::transaction(function () use ($application) {
            $member = Member::create([
                'gender' => $application->gender,
                'name' => $application->name,
                'surname' => $application->surname,
                'dateOfBirth' => $application->dateOfBirth?->toDateString(),
                'dateOfJoin' => $application->applied_on->toDateString(),
                'street' => $application->street ?: null,
                'zip' => $application->zip ?: null,
                ...($application->membership_kind ?? MembershipKind::None)->memberFlags(),
            ]);

            app(MembershipPeriodService::class)->openInitialPeriod($member, $application->applied_on->toDateString());
            app(MembershipFeeEntryService::class)->ensureEntriesForMember($member);

            MemberEmail::create(['memberID' => $member->memberID, 'email' => $application->email]);

            if (filled($application->phone)) {
                MemberPhone::create(['memberID' => $member->memberID, 'phoneCategory' => 1, 'phoneNumber' => $application->phone]);
            }

            $values = $application->custom_fields ?? [];

            if ($values !== []) {
                $fields = $this->memberFields->fields()->whereIn('fieldID', array_map('intval', array_keys($values)));
                $this->memberFields->save($member, $values, $fields);
            }

            $application->update([
                'status' => MemberApplicationStatus::Approved,
                'memberID' => $member->memberID,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            return $member;
        });

        $this->storePdf($application->refresh(), $member);

        ActivityLogger::log(
            'member.created',
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') wurde aus der Online-Anmeldung angelegt.',
            'Member',
            $member->memberID,
            ActivityLogger::created($member->activitySnapshot(), Member::activityLabels())
        );

        if ($sendWelcomeMail) {
            try {
                app(WelcomeMailService::class)->sendToMember($member);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $member;
    }

    public function reject(MemberApplication $application, ?string $note = null, bool $notifyApplicant = false): void
    {
        $application->update([
            'status' => MemberApplicationStatus::Rejected,
            'rejection_note' => filled($note) ? trim($note) : null,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        $application->deleteSignature();

        ActivityLogger::log(
            'member_application.rejected',
            'Online-Anmeldung von "'.$application->full_name.'" wurde abgelehnt.',
            'MemberApplication',
            $application->applicationID
        );

        if ($notifyApplicant) {
            $this->send($application->email, new MemberApplicationRejectedMail($application));
        }
    }

    public function delete(MemberApplication $application): void
    {
        $application->deleteSignature();
        Storage::disk('local')->deleteDirectory('applications/'.$application->applicationID);
        $application->delete();

        ActivityLogger::log(
            'member_application.deleted',
            'Online-Anmeldung von "'.$application->full_name.'" wurde gelöscht.',
            'MemberApplication',
            $application->applicationID
        );
    }

    /**
     * Löscht unbestätigte Anmeldungen, deren Link längst abgelaufen ist.
     */
    public function pruneUnverified(): int
    {
        $applications = MemberApplication::query()
            ->where('status', MemberApplicationStatus::Unverified)
            ->where('created_at', '<', now()->subDays(self::PRUNE_UNVERIFIED_AFTER_DAYS))
            ->get();

        foreach ($applications as $application) {
            $application->deleteSignature();
            Storage::disk('local')->deleteDirectory('applications/'.$application->applicationID);
            $application->delete();
        }

        return $applications->count();
    }

    /**
     * Empfänger neuer Anmeldungen: eingestellte Adressen, sonst alle Admins.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        $emails = $this->settings->notifyEmails();

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

    /**
     * Neue (bestätigte) Anmeldung: Verein benachrichtigen, Eingang bestätigen.
     */
    private function announce(MemberApplication $application): void
    {
        $recipients = $this->recipients();

        if ($recipients !== []) {
            $this->send($recipients, new MemberApplicationReceivedMail($application));
        }

        $this->send($application->email, new MemberApplicationConfirmationMail($application));
    }

    private function storePdf(MemberApplication $application, Member $member): void
    {
        try {
            $content = $this->pdf->render($application);
            $path = 'members/'.$member->memberID.'/beitrittserklaerung-'.$application->applicationID.'.pdf';

            Storage::disk('local')->put($path, $content);

            MemberFile::create([
                'memberID' => $member->memberID,
                'name' => 'Beitrittserklärung '.$application->applied_on->format('d.m.Y').'.pdf',
                'original_name' => 'beitrittserklaerung.pdf',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($content),
                'category' => 'Beitrittserklärung',
                'visible_to_member' => false,
                'uploaded_by' => Auth::id(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Versendet eine Mail und legt sie im Gesendet-Ordner des Vereins ab;
     * Fehler beim Versand werden protokolliert, aber nicht weitergereicht.
     *
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
            Log::warning('E-Mail zur Online-Anmeldung konnte nicht versendet werden.', [
                'mail' => $mail::class,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $exception) {
                Log::warning('E-Mail zur Online-Anmeldung konnte nicht im Gesendet-Ordner abgelegt werden.', [
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}

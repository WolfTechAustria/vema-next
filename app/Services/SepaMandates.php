<?php

namespace App\Services;

use App\Enums\PushCategory;
use App\Mail\SepaMandateGrantedMail;
use App\Models\Member;
use App\Models\SepaMandate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sepa\SepaValidator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SEPA-Lastschriftmandate: erteilen (online oder durch den Verein),
 * ersetzen und widerrufen.
 */
class SepaMandates
{
    public function creditorId(): ?string
    {
        return Setting::current()->sepa_creditor_id ?: null;
    }

    /**
     * Mandatstext mit Vereinsname und Gläubiger-ID – eigener Text aus den
     * Einstellungen oder der Standardwortlaut.
     */
    public function consentText(): string
    {
        $setting = Setting::current();
        $text = $setting->sepa_mandate_text ?: 'Ich ermächtige :verein (Gläubiger-ID :glaeubiger_id), Zahlungen von meinem Konto mittels SEPA-Lastschrift einzuziehen. '
            .'Zugleich weise ich mein Kreditinstitut an, die von :verein auf mein Konto gezogenen Lastschriften einzulösen. '
            .'Hinweis: Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. '
            .'Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.';

        return strtr($text, [
            ':verein' => (string) $setting->name,
            ':glaeubiger_id' => (string) $setting->sepa_creditor_id,
        ]);
    }

    /**
     * Neues Mandat; ein bisheriges aktives Mandat wird dabei widerrufen.
     */
    public function grant(
        Member $member,
        string $accountHolder,
        string $iban,
        ?string $bic,
        string $source,
        ?CarbonInterface $signedOn = null,
        bool $notifyClub = false,
    ): SepaMandate {
        $mandate = DB::transaction(function () use ($member, $accountHolder, $iban, $bic, $source, $signedOn) {
            $member->sepaMandates()->active()->update(['revoked_at' => now()]);

            return SepaMandate::create([
                'memberID' => $member->memberID,
                'reference' => $this->nextReference($member),
                'account_holder' => trim($accountHolder),
                'iban' => SepaValidator::normalize($iban),
                'bic' => filled($bic) ? SepaValidator::normalize($bic) : null,
                'signed_on' => $signedOn ?? today(),
                'source' => $source,
                'consent_text' => $source === SepaMandate::SOURCE_PORTAL ? $this->consentText() : null,
            ]);
        });

        ActivityLogger::log(
            $source === SepaMandate::SOURCE_PORTAL ? 'portal.sepa_mandate_granted' : 'sepa.mandate_created',
            'SEPA-Mandat '.$mandate->reference.' für "'.$member->full_name.'" (#'.$member->memberID.') wurde '
                .($source === SepaMandate::SOURCE_PORTAL ? 'online erteilt' : 'erfasst').' (IBAN '.$mandate->maskedIban().').',
            'Member',
            $member->memberID
        );

        if ($notifyClub) {
            $this->notifyClub($mandate);
        }

        return $mandate;
    }

    public function revoke(SepaMandate $mandate, bool $byMember = false): void
    {
        if (! $mandate->isActive()) {
            return;
        }

        $mandate->update(['revoked_at' => now(), 'revoked_by_member' => $byMember]);

        ActivityLogger::log(
            $byMember ? 'portal.sepa_mandate_revoked' : 'sepa.mandate_revoked',
            'SEPA-Mandat '.$mandate->reference.' von "'.$mandate->member?->full_name.'" wurde widerrufen.',
            'Member',
            $mandate->memberID
        );

        if ($byMember) {
            $this->notifyClub($mandate);
        }
    }

    /**
     * Admins und Kassiere mit E-Mail-Adresse.
     *
     * @return list<string>
     */
    public function treasurerEmails(): array
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'kassier']))
            ->where('enabled', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * „VEMA-12-3“: Mitgliedsnummer und laufende Nummer.
     */
    private function nextReference(Member $member): string
    {
        $count = SepaMandate::query()->where('memberID', $member->memberID)->count();

        do {
            $count++;
            $reference = 'VEMA-'.$member->memberID.'-'.$count;
        } while (SepaMandate::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function notifyClub(SepaMandate $mandate): void
    {
        $recipients = $this->treasurerEmails();

        if ($recipients !== []) {
            try {
                Mail::to($recipients)->send(new SepaMandateGrantedMail($mandate));
            } catch (Throwable $exception) {
                Log::warning('Hinweis zum SEPA-Mandat konnte nicht versendet werden.', ['error' => $exception->getMessage()]);
            }
        }

        app(PushNotifier::class)->toAdmins(
            PushCategory::Administration,
            $mandate->isActive() ? 'Neues SEPA-Mandat' : 'SEPA-Mandat widerrufen',
            $mandate->member?->full_name.($mandate->isActive() ? ' hat online ein Lastschriftmandat erteilt.' : ' hat das Lastschriftmandat widerrufen.'),
            route('members.show', $mandate->memberID),
            'sepa-mandate-'.$mandate->mandateID,
        );
    }
}

<?php

namespace App\Services;

use App\Enums\Feature;
use App\Mail\MemberWelcomeMail;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Willkommens-E-Mails für neue Mitglieder und neue Funktionäre. Der Text
 * kommt aus den Templates „member_welcome“ / „staff_welcome“; fehlt eine
 * Vorlage (z. B. deaktiviert), gilt der eingebaute Standardtext.
 */
class WelcomeMailService
{
    /**
     * @var array<string, array{subject: string, body_html: string}>
     */
    private const DEFAULT_TEXTS = [
        Template::MEMBER_WELCOME => [
            'subject' => 'Willkommen bei {{club_name}}',
            'body_html' => '<p>Hallo {{first_name}},</p>'
                .'<p>herzlich willkommen bei {{club_name}}! Schön, dass du dabei bist.</p>'
                .'<p>Unten findest du alles, was du für den Start brauchst. Bei Fragen melde dich jederzeit beim Vorstand.</p>',
        ],
        Template::STAFF_WELCOME => [
            'subject' => 'Dein Zugang zur Vereinsverwaltung von {{club_name}}',
            'body_html' => '<p>Hallo {{first_name}},</p>'
                .'<p>für dich wurde ein Zugang zur Vereinsverwaltung von {{club_name}} angelegt. '
                .'Damit kannst du – je nach deinen Berechtigungen – Mitglieder, Beiträge, Termine, Rundschreiben und mehr verwalten.</p>'
                .'<p>Lege zuerst über den Button unten dein Passwort fest. Danach meldest du dich jederzeit über die Login-Seite an.</p>',
        ],
    ];

    /**
     * Platzhalter je Vorlage für die Anzeige unter Kommunikation → Templates.
     *
     * @var array<string, array<string, string>>
     */
    public const PLACEHOLDERS = [
        Template::MEMBER_WELCOME => [
            '{{first_name}}' => 'Vorname',
            '{{last_name}}' => 'Nachname',
            '{{full_name}}' => 'Vollständiger Name',
            '{{salutation}}' => 'lieber / liebe',
            '{{club_name}}' => 'Vereinsname',
        ],
        Template::STAFF_WELCOME => [
            '{{first_name}}' => 'Vorname (bzw. Benutzername)',
            '{{last_name}}' => 'Nachname',
            '{{full_name}}' => 'Vollständiger Name',
            '{{username}}' => 'Benutzername',
            '{{club_name}}' => 'Vereinsname',
        ],
    ];

    /**
     * Schickt die Willkommens-E-Mail an alle E-Mail-Adressen des Mitglieds
     * (eine Nachricht). Liefert false, wenn keine Adresse hinterlegt ist.
     */
    public function sendToMember(Member $member): bool
    {
        $emails = $member->emails()->pluck('email')->filter()->unique()->values()->all();

        if ($emails === []) {
            return false;
        }

        $text = $this->render(Template::MEMBER_WELCOME, $this->memberPlaceholders($member));
        $mail = new MemberWelcomeMail($member, $text['subject'], $text['body']);

        $rawMessage = null;
        $mail->withSymfonyMessage(function ($message) use (&$rawMessage) {
            $rawMessage = $message->toString();
        });

        Mail::to($emails)->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $exception) {
                Log::warning('Willkommens-E-Mail konnte nicht im Gesendet-Ordner abgelegt werden.', [
                    'memberID' => $member->memberID,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        ActivityLogger::log(
            'member.welcome_sent',
            'Willkommens-E-Mail an Mitglied "'.$member->full_name.'" (#'.$member->memberID.') an '.implode(', ', $emails).' versendet.',
            'Member',
            $member->memberID
        );

        return true;
    }

    /**
     * Betreff und Text der Willkommens-E-Mail für einen neuen Funktionär.
     *
     * @return array{subject: string, body: string}
     */
    public function staffWelcome(User $user): array
    {
        $member = $user->memberID ? Member::query()->find($user->memberID) : null;

        return $this->render(Template::STAFF_WELCOME, [
            '{{first_name}}' => $member?->name ?: $user->username,
            '{{last_name}}' => $member?->surname ?? '',
            '{{full_name}}' => $member?->full_name ?: $user->username,
            '{{username}}' => $user->username,
            '{{club_name}}' => $this->clubName(),
        ]);
    }

    public function memberPortalAvailable(): bool
    {
        return app(PlanEntitlements::class)->allows(Feature::MemberPortal);
    }

    public function whatsappCommunityUrl(): ?string
    {
        return Setting::current()->whatsapp_community_url ?: null;
    }

    /**
     * @return array<string, string>
     */
    private function memberPlaceholders(Member $member): array
    {
        $isMale = in_array(mb_strtolower(trim((string) $member->gender)), ['herr', 'm', 'male', 'männlich'], true);

        return [
            '{{first_name}}' => $member->name ?? '',
            '{{last_name}}' => $member->surname ?? '',
            '{{full_name}}' => $member->full_name,
            '{{salutation}}' => $isMale ? 'lieber' : 'liebe',
            '{{club_name}}' => $this->clubName(),
        ];
    }

    /**
     * @param  array<string, string>  $placeholders
     * @return array{subject: string, body: string}
     */
    private function render(string $key, array $placeholders): array
    {
        $template = Template::query()->where('key', $key)->where('active', true)->first();

        $subject = $template?->subject ?: self::DEFAULT_TEXTS[$key]['subject'];
        $body = $template?->body_html ?: self::DEFAULT_TEXTS[$key]['body_html'];

        // Werte im HTML-Text maskieren, im Betreff (Klartext) nicht.
        $escapedPlaceholders = array_map(fn (string $value) => e($value), $placeholders);

        return [
            'subject' => strtr($subject, $placeholders),
            'body' => strtr($body, $escapedPlaceholders),
        ];
    }

    private function clubName(): string
    {
        return Setting::current()->name ?: 'unserem Verein';
    }
}

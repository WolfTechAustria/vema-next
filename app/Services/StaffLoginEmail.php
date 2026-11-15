<?php

namespace App\Services;

use App\Mail\StaffLoginEmailChangedMail;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Login-E-Mail eines Funktionärszugangs ändern — von Admins in der
 * Benutzerverwaltung oder automatisch, wenn die Adresse in den
 * Mitgliedsdaten geändert wird. Die bisherige Adresse bekommt einen
 * Sicherheitshinweis.
 */
class StaffLoginEmail
{
    public const REASON_MEMBER = 'Mitgliedsdaten';

    public const REASON_ADMIN = 'Benutzerverwaltung';

    /**
     * Liefert false, wenn sich die Adresse nicht ändert.
     */
    public function change(User $user, string $newEmail, string $reason): bool
    {
        $newEmail = trim($newEmail);
        $oldEmail = (string) $user->email;

        if (Str::lower($newEmail) === Str::lower($oldEmail)) {
            return false;
        }

        // Offene Passwort-Links gelten für die alte Adresse.
        Password::getRepository()->delete($user);

        $user->update(['email' => $newEmail]);

        ActivityLogger::log(
            'user.email_changed',
            'Login-E-Mail von Benutzer "'.$user->username.'" wurde geändert ('.$reason.').',
            'User',
            $user->id,
            ActivityLogger::changes(['email' => $oldEmail], ['email' => $newEmail], ['email' => 'Login-E-Mail'])
        );

        if ($oldEmail !== '') {
            $this->notifyOldAddress($user, $oldEmail, $newEmail);
        }

        return true;
    }

    /**
     * Nach dem Speichern der Mitgliedsdaten: Zugänge, deren Login-Adresse
     * geändert oder entfernt wurde, ziehen nach.
     *
     * @param  array<int, string>  $emailsBefore  MemberEmail-ID => Adresse vor dem Speichern
     * @return array{changed: list<string>, warnings: list<string>}
     */
    public function syncFromMember(Member $member, array $emailsBefore): array
    {
        $result = ['changed' => [], 'warnings' => []];

        $emailsAfter = array_filter(array_map(fn ($email) => trim((string) $email), $this->memberEmails($member)));

        $users = User::query()->where('memberID', $member->memberID)->get();

        foreach ($users as $user) {
            $login = Str::lower(trim((string) $user->email));

            $matchingIds = array_keys(array_filter($emailsBefore, fn ($email) => Str::lower(trim((string) $email)) === $login));

            // Login-Adresse stand nicht in den Mitgliedsdaten oder ist weiterhin vorhanden.
            if ($login === '' || $matchingIds === [] || in_array($login, array_map(Str::lower(...), $emailsAfter), true)) {
                continue;
            }

            $renamedId = collect($matchingIds)->first(fn (int $id) => isset($emailsAfter[$id]));
            $newEmail = $renamedId !== null ? $emailsAfter[$renamedId] : (reset($emailsAfter) ?: null);

            if ($newEmail === null) {
                $result['warnings'][] = 'Das Mitglied hat keine E-Mail-Adresse mehr – die Login-E-Mail von „'.$user->username.'“ bleibt '.$user->email.'.';

                continue;
            }

            if ($this->change($user, $newEmail, self::REASON_MEMBER)) {
                $result['changed'][] = 'Login-E-Mail von „'.$user->username.'“ ist jetzt '.$newEmail.'.';
            }
        }

        return $result;
    }

    /**
     * Adressen des Mitglieds in Reihenfolge der Anlage.
     *
     * @return array<int, string> MemberEmail-ID => Adresse
     */
    public function memberEmails(Member $member): array
    {
        // Legacy-Spalte heißt "ID" — per Alias unabhängig von der Groß-/Kleinschreibung.
        return MemberEmail::query()
            ->where('memberID', $member->memberID)
            ->selectRaw('ID as email_id, email')
            ->orderBy('email_id')
            ->get()
            ->mapWithKeys(fn (MemberEmail $email) => [(int) $email->email_id => (string) $email->email])
            ->all();
    }

    private function notifyOldAddress(User $user, string $oldEmail, string $newEmail): void
    {
        $mail = new StaffLoginEmailChangedMail($user, $newEmail);
        $rawMessage = null;

        $mail->withSymfonyMessage(function ($message) use (&$rawMessage) {
            $rawMessage = $message->toString();
        });

        try {
            Mail::to($oldEmail)->send($mail);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append($rawMessage);
            } catch (Throwable $imapException) {
                Log::warning('Hinweis zur geänderten Login-E-Mail konnte nicht im IMAP-Gesendet-Ordner gespeichert werden.', [
                    'userID' => $user->id,
                    'error' => $imapException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Adresse für Hinweise teilweise verdeckt: a***@example.at
     */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***'.($domain !== '' ? '@'.$domain : '');
    }
}

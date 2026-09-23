<?php

namespace App\Livewire\MemberAuth;

use App\Mail\MemberMagicLoginMail;
use App\Models\Member;
use App\Models\MemberLoginToken;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    /**
     * Maximale Anzahl an Login-Link-Anfragen je E-Mail-Adresse und IP
     * innerhalb von zehn Minuten.
     */
    private const MAX_LINK_REQUESTS = 3;

    public string $email = '';

    /**
     * Rückmeldung nach dem Anfordern des Links.
     *
     * Heißt bewusst nicht $message: @error/@enderror im Blade-Template
     * überschreibt und entfernt eine gleichnamige Variable.
     */
    public ?string $statusMessage = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc,filter',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein (z. B. name@beispiel.at).',
            'email.max' => 'Die E-Mail-Adresse darf höchstens 255 Zeichen lang sein.',
        ];
    }

    public function updatedEmail(): void
    {
        $this->email = trim($this->email);
        $this->statusMessage = null;

        if ($this->email !== '') {
            $this->validateOnly('email');
        } else {
            $this->resetErrorBag('email');
        }
    }

    public function requestLoginLink(): void
    {
        $this->email = trim($this->email);
        $this->statusMessage = null;

        $this->validate();

        $throttleKey = 'member-login-link:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LINK_REQUESTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            $this->addError(
                'email',
                'Es wurden bereits mehrere Login-Links angefordert. Bitte prüfe dein Postfach (auch den Spam-Ordner) oder versuche es in '.$minutes.' '.($minutes === 1 ? 'Minute' : 'Minuten').' erneut.'
            );

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $member = Member::query()
            ->where('active', 1)
            ->whereHas('emails', function ($query) {
                $query->where('email', $this->email);
            })
            ->first();

        /*
         * Keine Information darüber preisgeben,
         * ob die Adresse tatsächlich existiert.
         */
        if (! $member) {
            $this->statusMessage =
                'Wenn diese E-Mail-Adresse bei uns hinterlegt ist, wurde ein Login-Link versendet.';

            return;
        }

        /*
         * Alte noch offene Tokens dieses Mitglieds unbrauchbar machen.
         */
        MemberLoginToken::query()
            ->where('memberID', $member->memberID)
            ->whereNull('used_at')
            ->delete();

        /*
         * Nur der Hash landet in der Datenbank.
         * Der echte Token wird ausschließlich per E-Mail verschickt.
         */
        $plainToken = Str::random(64);

        MemberLoginToken::create([
            'memberID' => $member->memberID,
            'email' => $this->email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(30),
        ]);

        $mail = new MemberMagicLoginMail(
            member: $member,
            token: $plainToken,
        );

        $rawMessage = null;

        $mail->withSymfonyMessage(
            function ($message) use (&$rawMessage) {
                $rawMessage = $message->toString();
            }
        );

        Mail::to($this->email)->send($mail);

        if ($rawMessage) {
            try {
                app(ImapSentMailService::class)->append(
                    $rawMessage
                );
            } catch (\Throwable $imapException) {

                \Log::warning(
                    'Login-Link wurde versendet, konnte aber nicht im IMAP-Gesendet-Ordner gespeichert werden.',
                    [
                        'memberID' => $member->memberID,
                        'error' => $imapException->getMessage(),
                    ]
                );
            }
        }

        $this->statusMessage =
            'Wenn diese E-Mail-Adresse bei uns hinterlegt ist, wurde ein Login-Link versendet.';
    }

    public function render()
    {
        return view('livewire.member-auth.login')
            ->layout('layouts.member-auth', [
                'title' => 'Mitgliederbereich | VEMA',
            ]);
    }
}

<?php

namespace App\Livewire\MemberAuth;

use App\Mail\MemberMagicLoginMail;
use App\Models\Member;
use App\Models\MemberLoginToken;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public ?string $message = null;

    public function requestLoginLink(): void
    {
        $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

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
            $this->message =
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

        $this->message =
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

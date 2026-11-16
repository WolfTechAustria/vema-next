@include('emails.member-application.box-start', ['title' => 'Ihr Anmeldecode'])

<p style="margin: 0 0 12px 0;">
    Für die Anmeldung mit dem Zugang <strong>{{ $user->username }}</strong> geben Sie bitte diesen Code ein:
</p>

<p style="margin: 0 0 16px 0; font-size: 28px; font-weight: bold; letter-spacing: 6px;">
    {{ $code }}
</p>

<p style="margin: 0 0 12px 0;">
    Der Code ist {{ $validMinutes }} Minuten gültig und kann nur einmal verwendet werden.
</p>

<p style="margin: 0;">
    Falls Sie sich nicht gerade anmelden, hat jemand Ihr Passwort verwendet. Ändern Sie es bitte umgehend und informieren Sie einen Administrator Ihres Vereins.
</p>

@include('emails.member-application.box-end')

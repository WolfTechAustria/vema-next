@include('emails.member-application.box-start', ['title' => 'Bitte bestätige deine Anmeldung'])

<p style="margin: 0 0 12px 0;">Hallo {{ $application->name }},</p>

<p style="margin: 0 0 12px 0;">
    vielen Dank für deine Anmeldung als Mitglied bei „{{ $clubName }}“. Bitte bestätige mit dem folgenden Link deine E-Mail-Adresse,
    damit wir deine Anmeldung bearbeiten können.
</p>

@include('emails.member-application.button', ['url' => $verifyUrl, 'label' => 'Anmeldung bestätigen'])

<p style="margin: 0; font-size: 13px; color: #6b7280;">
    Der Link ist {{ $hours }} Stunden gültig. Wenn du dich nicht angemeldet hast, kannst du diese E-Mail ignorieren.
</p>

@include('emails.member-application.box-end')

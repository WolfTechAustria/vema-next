@include('emails.member-application.box-start', ['title' => 'Deine Anmeldung ist eingegangen'])

<p style="margin: 0 0 12px 0;">Hallo {{ $application->name }},</p>

<p style="margin: 0 0 12px 0;">
    vielen Dank, deine Anmeldung als Mitglied bei „{{ $clubName }}“ ist eingegangen. Wir prüfen sie und melden uns bei dir.
    Deine Angaben im Überblick:
</p>

@include('emails.member-application.summary')

@include('emails.member-application.box-end')

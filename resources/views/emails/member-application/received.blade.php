@include('emails.member-application.box-start', ['title' => 'Neue Online-Anmeldung'])

<p style="margin: 0 0 12px 0;">
    {{ $application->full_name }} hat sich online als Mitglied angemeldet. Die Anmeldung wartet auf die Freigabe.
</p>

@include('emails.member-application.summary')

@include('emails.member-application.button', ['url' => $applicationUrl, 'label' => 'Anmeldung in VEMA öffnen'])

@include('emails.member-application.box-end')

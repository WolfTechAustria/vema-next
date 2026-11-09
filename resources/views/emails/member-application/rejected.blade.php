@include('emails.member-application.box-start', ['title' => 'Deine Anmeldung'])

<p style="margin: 0 0 12px 0;">Hallo {{ $application->name }},</p>

<p style="margin: 0 0 12px 0;">
    leider können wir deine Anmeldung als Mitglied bei „{{ $clubName }}“ nicht annehmen.
</p>

@if($application->rejection_note)
    <p style="margin: 0 0 12px 0; white-space: pre-line;">{{ $application->rejection_note }}</p>
@endif

@include('emails.member-application.box-end')

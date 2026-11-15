@include('emails.member-application.box-start', ['title' => 'Login-E-Mail wurde geändert'])

<p style="margin: 0 0 12px 0;">
    Die Login-E-Mail Ihres VEMA-Zugangs <strong>{{ $user->username }}</strong> wurde am {{ $changedAt }} auf <strong>{{ $maskedEmail }}</strong> geändert.
</p>

<p style="margin: 0 0 12px 0;">
    Anmeldung, Passwort-Links und Hinweise gehen ab sofort an die neue Adresse. Sie können sich weiterhin auch mit Ihrem Benutzernamen anmelden.
</p>

<p style="margin: 0 0 16px 0;">
    Falls Sie diese Änderung nicht veranlasst haben, wenden Sie sich bitte an einen Administrator Ihres Vereins.
</p>

@include('emails.member-application.button', ['url' => $loginUrl, 'label' => 'Zur Anmeldung'])

@include('emails.member-application.box-end')

@extends('emails.central.layout')

@section('title', 'Dein Zugang zu VEMA')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $tenant->contact_name }},
    </p>

    <p style="margin:0 0 16px 0;">
        hier ist ein neuer Zugangslink für <strong>{{ $tenant->name }}</strong> unter
        <a href="{{ $tenant->url() }}" style="color:#0f172a;">{{ $tenant->host() }}</a>.
    </p>

    <p style="margin:0 0 16px 0;">
        Dein Benutzername ist <strong>{{ $username }}</strong>. Lege jetzt dein Passwort fest:
    </p>

    @include('emails.central.partials.button', ['url' => $passwordSetupUrl, 'label' => 'Passwort festlegen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Der Link ist {{ config('auth.passwords.users.expire') }} Minuten gültig. Danach kannst du auf der
        Login-Seite „Passwort vergessen?“ verwenden.
    </p>
@endsection

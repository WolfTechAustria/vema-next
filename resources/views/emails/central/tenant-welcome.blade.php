@extends('emails.central.layout')

@section('title', 'Willkommen bei VEMA')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $tenant->contact_name }},
    </p>

    <p style="margin:0 0 16px 0;">
        <strong>{{ $tenant->name }}</strong> ist eingerichtet und ab sofort erreichbar unter
        <a href="{{ $tenant->url() }}" style="color:#0f172a;">{{ $tenant->host() }}</a>.
        Bis {{ $tenant->trial_ends_at?->format('d.m.Y') }} kannst du alle Funktionen kostenlos testen.
    </p>

    <p style="margin:0 0 16px 0;">
        Dein Benutzername ist <strong>{{ $tenant->contact_email }}</strong>. Lege jetzt dein Passwort fest:
    </p>

    @include('emails.central.partials.button', ['url' => $passwordSetupUrl, 'label' => 'Passwort festlegen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Der Link ist {{ config('auth.passwords.users.expire') }} Minuten gültig. Danach kannst du auf der
        Login-Seite „Passwort vergessen?“ verwenden.
    </p>
@endsection

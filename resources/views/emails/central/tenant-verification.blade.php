@extends('emails.central.layout')

@section('title', 'Registrierung bestätigen')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $tenant->contact_name }},
    </p>

    <p style="margin:0 0 16px 0;">
        danke für die Registrierung von <strong>{{ $tenant->name }}</strong> bei VEMA.
        Bitte bestätige deine E-Mail-Adresse — danach wird euer Verein unter
        <strong>{{ $tenant->host() }}</strong> sofort eingerichtet und die
        {{ config('tenancy.trial_days') }}-tägige Testphase mit allen Funktionen beginnt.
    </p>

    @include('emails.central.partials.button', ['url' => $verificationUrl, 'label' => 'E-Mail-Adresse bestätigen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Der Link ist {{ config('tenancy.verification_hours') }} Stunden gültig.
        Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.
    </p>
@endsection

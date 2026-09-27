@extends('emails.central.layout')

@section('title', 'Deine Vereine bei VEMA')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo,
    </p>

    <p style="margin:0 0 16px 0;">
        du hast die Anmelde-Adressen deiner Vereine angefordert:
    </p>

    <ul style="margin:0 0 16px 0; padding-left:20px;">
        @foreach($tenants as $tenant)
            <li style="margin-bottom:8px;">
                <strong>{{ $tenant->name }}</strong><br>
                <a href="{{ $tenant->url() }}/login" style="color:#0f172a;">{{ $tenant->host() }}</a>
            </li>
        @endforeach
    </ul>

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Falls du diese E-Mail nicht angefordert hast, kannst du sie ignorieren.
    </p>
@endsection

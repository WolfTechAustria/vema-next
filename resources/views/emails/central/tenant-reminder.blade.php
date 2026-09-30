@extends('emails.central.layout')

@section('title', str_starts_with($reminder, 'trial') ? 'Eure Testphase' : 'Eure Lizenz')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $tenant->contact_name }},
    </p>

    @if($reminder === 'trial-ended')
        <p style="margin:0 0 16px 0;">
            die kostenlose Testphase von <strong>{{ $tenant->name }}</strong> ist am
            {{ $tenant->trial_ends_at->format('d.m.Y') }} zu Ende gegangen. Ihr arbeitet jetzt im Paket
            <strong>{{ $tenant->plan->label() }}</strong> weiter — alle Daten bleiben erhalten.
        </p>

        <p style="margin:0 0 16px 0;">
            Funktionen außerhalb dieses Pakets sind ausgeblendet.
            @if($tenant->plan->memberLimit())
                Es können bis zu {{ $tenant->plan->memberLimit() }} aktive Mitglieder verwaltet werden.
            @endif
            Mit einem größeren Paket ist alles sofort wieder verfügbar.
        </p>
    @elseif(str_starts_with($reminder, 'trial'))
        <p style="margin:0 0 16px 0;">
            die kostenlose Testphase von <strong>{{ $tenant->name }}</strong> endet am
            <strong>{{ $tenant->trial_ends_at->format('d.m.Y') }}</strong>. Danach geht es automatisch im Paket
            <strong>{{ $tenant->plan->label() }}</strong> weiter — ohne Kosten, eure Daten bleiben erhalten.
        </p>

        <p style="margin:0 0 16px 0;">
            Wenn ihr weiterhin alle Funktionen nutzen wollt, antwortet einfach auf diese E-Mail oder wählt ein Paket auf unserer Website.
        </p>
    @else
        <p style="margin:0 0 16px 0;">
            die Lizenz von <strong>{{ $tenant->name }}</strong> ist gültig bis
            <strong>{{ $tenant->license_valid_until->format('d.m.Y') }}</strong>. Damit ihr ohne Unterbrechung weiterarbeiten könnt,
            verlängert sie bitte rechtzeitig — antwortet dazu einfach auf diese E-Mail.
        </p>
    @endif

    @include('emails.central.partials.button', ['url' => config('tenancy.website_url').'/#preise', 'label' => 'Pakete ansehen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Zu eurem Verein: <a href="{{ $tenant->url() }}/login" style="color:#6b7280;">{{ $tenant->host() }}</a>
    </p>
@endsection

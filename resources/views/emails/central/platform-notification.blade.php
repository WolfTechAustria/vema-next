@extends('emails.central.layout')

@section('title', $event)

@section('content')
    <table cellpadding="4" cellspacing="0" border="0" style="font-size:14px;">
        <tr><td style="color:#6b7280;">Verein</td><td><strong>{{ $tenant->name }}</strong></td></tr>
        <tr><td style="color:#6b7280;">Adresse</td><td>{{ $tenant->host() }}</td></tr>
        <tr><td style="color:#6b7280;">Kontakt</td><td>{{ $tenant->contact_name }} &lt;{{ $tenant->contact_email }}&gt;</td></tr>
        <tr><td style="color:#6b7280;">Gewünschtes Paket</td><td>{{ $tenant->requested_plan?->label() ?? '–' }}</td></tr>
        <tr><td style="color:#6b7280;">Status</td><td>{{ $tenant->status->label() }}</td></tr>
    </table>

    @if($details)
        <p style="margin:16px 0 0 0; padding:12px; background:#fef2f2; border-radius:6px; font-size:13px; color:#991b1b;">
            {{ $details }}
        </p>
    @endif
@endsection

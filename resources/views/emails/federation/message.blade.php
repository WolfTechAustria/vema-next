@extends('emails.central.layout')

@section('title', $federationMessage->subject)

@section('content')
    <p style="margin:0 0 16px 0; font-size:13px; color:#6b7280;">
        Mitteilung von {{ $federation->name }} an {{ $club->name }}
    </p>

    <div style="margin:0 0 16px 0;">
        {!! nl2br(e($federationMessage->body)) !!}
    </div>

    @if($federationMessage->attachments->isNotEmpty())
        <p style="margin:0 0 16px 0;">
            Anhänge: {{ $federationMessage->attachments->pluck('file_name')->join(', ') }}
        </p>
    @endif

    @include('emails.central.partials.button', ['url' => $inboxUrl, 'label' => 'In VEMA öffnen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Alle Mitteilungen und Dokumente des Verbands findet ihr in VEMA unter „Verband“.
    </p>
@endsection

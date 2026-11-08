@extends('emails.central.layout')

@section('title', $news->title)

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $tenant->contact_name }},
    </p>

    <div style="margin:0 0 16px 0;">
        {{ $news->bodyHtml() }}
    </div>

    @include('emails.central.partials.button', ['url' => $newsUrl, 'label' => 'In VEMA ansehen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Alle Neuigkeiten finden die Administratoren von {{ $tenant->name }} in VEMA unter „Neuigkeiten“.
    </p>
@endsection

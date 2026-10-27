@extends('emails.central.layout')

@section('title', 'Einladung in den Verband')

@section('content')
    <p style="margin:0 0 16px 0;">
        Hallo {{ $club->contact_name }},
    </p>

    <p style="margin:0 0 16px 0;">
        <strong>{{ $federation->name }}</strong> lädt <strong>{{ $club->name }}</strong> ein, sich in VEMA mit dem Verband zu verbinden.
    </p>

    <p style="margin:0 0 16px 0;">
        Der Verband sieht dann Kennzahlen eures Vereins, z. B. die Anzahl der Mitglieder nach Alter und Geschlecht, aber keine Namen oder Kontaktdaten.
        Mitteilungen, Termine und Dokumente des Verbands bekommt ihr direkt in eure Vereinsverwaltung. Austreten könnt ihr jederzeit.
    </p>

    @include('emails.central.partials.button', ['url' => $acceptUrl, 'label' => 'Einladung ansehen'])

    <p style="margin:0; font-size:13px; color:#6b7280;">
        Annehmen oder ablehnen können die Administratoren eures Vereins unter Einstellungen → Verbände.
    </p>
@endsection

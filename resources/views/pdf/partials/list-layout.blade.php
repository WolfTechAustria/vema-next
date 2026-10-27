{{--
    Gemeinsames Layout für Listen (Dienstplan, Geburtstagsliste): Seitenränder
    aus den Einstellungen, Titel-Stile und das Vereinslogo rechts oben auf
    jeder Seite. Mit hinterlegtem Listen-Hintergrund entfällt das Logo, weil
    der Hintergrund es mitbringt (er wird erst danach per FPDI untergelegt).

    Einbinden: im <head> @include('pdf.partials.list-layout', ['part' => 'styles']),
    direkt nach <body> @include('pdf.partials.list-layout', ['part' => 'logo']),
    danach @include('pdf.partials.list-layout', ['part' => 'header', 'listTitle' => …, 'listSubtitle' => …]).
--}}
@php
    $listBranding = app(\App\Services\ClubBranding::class);
    $listMargins = $listBranding->listMargins();
    $listLogoPath = $listBranding->listBackgroundPath() === null
        ? $listBranding->logoPath()
        : null;
@endphp

@if($part === 'styles')
    <style>
        @page {
            margin: {{ $listMargins['top'] }}mm {{ $listMargins['right'] }}mm {{ $listMargins['bottom'] }}mm {{ $listMargins['left'] }}mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #111827;
        }

        .list-title {
            margin: 0;
            font-size: 15pt;
            font-weight: bold;
        }

        .list-subtitle {
            margin: 1mm 0 6mm 0;
            font-size: 8pt;
            font-weight: bold;
        }

        .list-logo {
            position: fixed;
            top: 0;
            right: 0;
        }

        .list-logo img {
            max-height: 18mm;
            max-width: 30mm;
        }

        .list-table {
            border-collapse: collapse;
        }

        .list-table.full-width {
            width: 100%;
        }

        .list-table td {
            padding: 0.6mm 4mm 0.6mm 0;
            vertical-align: top;
        }

        .list-table td.nowrap {
            white-space: nowrap;
        }

        .list-header-with-logo {
            padding-right: 35mm;
        }

        .list-table .highlight td {
            font-weight: bold;
        }
    </style>
@elseif($part === 'logo' && $listLogoPath)
    <div class="list-logo">
        <img src="{{ $listLogoPath }}" alt="Vereinslogo">
    </div>
@elseif($part === 'header')
    <div @class(['list-header-with-logo' => $listLogoPath !== null])>
        <h1 class="list-title">{{ $listTitle }}</h1>

        <div class="list-subtitle">{{ $listSubtitle }}</div>
    </div>
@endif

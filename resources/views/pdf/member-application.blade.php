<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <title>Beitrittserklärung {{ $application->full_name }}</title>

    @include('pdf.partials.list-layout', ['part' => 'styles'])

    <style>
        body {
            font-size: 10pt;
        }

        .section {
            margin-top: 6mm;
        }

        .section-title {
            margin: 0 0 2mm 0;
            font-size: 10.5pt;
            font-weight: bold;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table td {
            padding: 1.2mm 0;
            border-bottom: 0.2mm solid #d1d5db;
            vertical-align: top;
        }

        .data-table td.label {
            width: 45mm;
            color: #4b5563;
        }

        .text {
            white-space: pre-line;
        }

        .muted {
            color: #6b7280;
            font-size: 8.5pt;
        }

        .signature img {
            max-height: 25mm;
            max-width: 80mm;
        }

        .signature-line {
            width: 80mm;
            border-top: 0.2mm solid #111827;
            padding-top: 1mm;
        }
    </style>
</head>

<body>

@include('pdf.partials.list-layout', ['part' => 'logo'])

@include('pdf.partials.list-layout', [
    'part' => 'header',
    'listTitle' => 'Beitrittserklärung',
    'listSubtitle' => $clubName,
])

@if($intro)
    <div class="text">{{ $intro }}</div>
@endif

<div class="section">
    <p class="section-title">Angaben zur Person</p>

    <table class="data-table">
        @foreach($rows as $label => $value)
            <tr>
                <td class="label">{{ $label }}</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</div>

@if($application->membership_type)
    <div class="section">
        <p class="section-title">Mitgliedsart</p>

        <div>[x] {{ $application->membership_type }}</div>

        @if($membershipInfo)
            <div class="muted text">{{ $membershipInfo }}</div>
        @endif
    </div>
@endif

<div class="section">
    <table class="data-table">
        <tr>
            <td class="label">Beitrittsdatum</td>
            <td>{{ $application->applied_on->format('d.m.Y') }}</td>
        </tr>
        <tr>
            <td class="label">Ort, Datum</td>
            <td>{{ $application->place ? $application->place.', ' : '' }}{{ $application->applied_on->format('d.m.Y') }}</td>
        </tr>
        @if($application->guardian_name)
            <tr>
                <td class="label">Erziehungsberechtigte(r)</td>
                <td>{{ $application->guardian_name }}</td>
            </tr>
        @endif
    </table>
</div>

@if($signature)
    <div class="section signature">
        <img src="{{ $signature }}" alt="Unterschrift">

        <div class="signature-line muted">
            Unterschrift{{ $application->guardian_name ? ' der/des Erziehungsberechtigten' : '' }}
        </div>
    </div>
@endif

@if($privacyText)
    <div class="section">
        <p class="section-title">Datenschutz</p>

        <div class="text muted">{{ $privacyText }}</div>
    </div>
@endif

@if(! empty($application->consents))
    <div class="section">
        <p class="section-title">Zustimmungen</p>

        @foreach($application->consents as $consent)
            <div>{{ ($consent['accepted'] ?? false) ? '[x]' : '[ ]' }} {{ $consent['text'] ?? '' }}</div>
        @endforeach
    </div>
@endif

<div class="section muted">
    Online eingereicht am {{ $application->created_at->timezone(config('app.club_timezone'))->format('d.m.Y, H:i') }} Uhr
    @if($application->verified_at)
        · E-Mail-Adresse bestätigt am {{ $application->verified_at->timezone(config('app.club_timezone'))->format('d.m.Y, H:i') }} Uhr
    @endif
    @if($application->ip)
        · IP {{ $application->ip }}
    @endif
</div>

</body>
</html>

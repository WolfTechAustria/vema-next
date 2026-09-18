<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #111827;
        }

        h1 {
            margin: 0 0 3mm 0;
            font-size: 16pt;
        }

        .meta {
            margin-bottom: 5mm;
            color: #6b7280;
            font-size: 8pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            font-size: 8.5pt;
            padding: 2.5mm;
            border-bottom: 1px solid #d1d5db;
        }

        td {
            padding: 2.5mm;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .nowrap {
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 0.5mm 2mm;
            border-radius: 3mm;
            font-size: 7.5pt;
        }

        .badge-round {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-half-round {
            background: #e0f2fe;
            color: #075985;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        .header-table td {
            border: 0;
            padding: 0;
        }

        .header-title {
            width: 75%;
            vertical-align: middle;
        }

        .header-logo {
            width: 25%;
            text-align: right;
            vertical-align: middle;
        }

        .header-logo img {
            max-height: 18mm;
            max-width: 45mm;
        }

        .header-title h1 {
            margin: 0 0 2mm 0;
        }
    </style>
</head>

<body>

<table class="header-table">
    <tr>
        <td class="header-title">
            <h1>Geburtstage im {{ $monthName }}</h1>

            <div class="meta">
                Stand: {{ now()->format('d.m.Y') }}
                &nbsp;|&nbsp;
                Anzahl: {{ $members->count() }}
            </div>
        </td>

        <td class="header-logo">
            <img
                src="{{ public_path('images/logo.jpg') }}"
                alt="Vereinslogo"
            >
        </td>
    </tr>
</table>

<table>
    <thead>
    <tr>
        <th>Anrede</th>
        <th>Nachname</th>
        <th>Vorname</th>
        <th>Geburtsdatum</th>
        <th class="center">Alter</th>
        <th></th>
    </tr>
    </thead>

    <tbody>
    @forelse($members as $row)
        @php $member = $row['member']; @endphp

        <tr>
            <td class="nowrap">
                @php
                    $salutation = match (strtolower((string) $member->gender)) {
                        'm', 'male', 'mann', 'männlich' => 'Herr',
                        'w', 'f', 'female', 'frau', 'weiblich' => 'Frau',
                        default => '—',
                    };
                @endphp
                {{ $salutation }}
            </td>

            <td>{{ $member->surname }}</td>

            <td>{{ $member->name }}</td>

            <td class="nowrap">
                {{ \Carbon\Carbon::parse($member->dateOfBirth)->format('d.m.Y') }}
            </td>

            <td class="center">
                {{ $row['age'] }}
            </td>

            <td class="nowrap">
                @if ($row['isRound'])
                    <span class="badge badge-round">rund</span>
                @elseif ($row['isHalfRound'])
                    <span class="badge badge-half-round">halbrund</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="muted">
                Keine Geburtstage aktiver Mitglieder in diesem Monat.
            </td>
        </tr>
    @endforelse
    </tbody>
</table>

</body>
</html>

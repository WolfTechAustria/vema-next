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
            font-size: 8pt;
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
            font-size: 7.5pt;
            padding: 2mm;
            border-bottom: 1px solid #d1d5db;
        }

        td {
            padding: 2mm;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .nowrap {
            white-space: nowrap;
        }

        .muted {
            color: #6b7280;
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
            <h1>Aktuelle Mitglieder</h1>

            <div class="meta">
                Stand: {{ now()->format('d.m.Y') }}
                &nbsp;|&nbsp;
                Anzahl Mitglieder: {{ $members->count() }}
            </div>
        </td>

        <td class="header-logo">
            <img
                src="{{ public_path('images/logo.jpg') }}"
                alt="{{ \App\Models\Setting::current()->name }}"
            >
        </td>
    </tr>
</table>

<table>
    <thead>
    <tr>
        <th>Mitglied</th>
        <th>Adresse</th>
        <th>Telefon</th>
        <th>E-Mail</th>
        <th>Geburtsdatum</th>
        <th>Mitglied seit</th>
    </tr>
    </thead>

    <tbody>
    @foreach($members as $member)
        <tr>
            <td class="nowrap">
                {{ $member->surname }}
                {{ $member->name }}
            </td>



            <td>
                {{ $member->street }}<br>

                {{ $member->zip }}
                {{ $member->city?->city }}
            </td>

            <td>
                @forelse($member->phones as $phone)
                    {{ $phone->phoneNumber ?? '' }}

                    @if(!$loop->last)
                        <br>
                    @endif
                @empty
                    <span class="muted">—</span>
                @endforelse
            </td>

            <td>
                @forelse($member->emails as $email)
                    {{ $email->email }}

                    @if(!$loop->last)
                        <br>
                    @endif
                @empty
                    <span class="muted">—</span>
                @endforelse
            </td>

            <td class="nowrap">
                @if($member->dateOfBirth)
                    {{ \Carbon\Carbon::parse($member->dateOfBirth)->format('d.m.Y') }}
                @else
                    <span class="muted">—</span>
                @endif
            </td>

            <td class="nowrap">
                @if($member->dateOfJoin)
                    {{ \Carbon\Carbon::parse($member->dateOfJoin)->format('d.m.Y') }}
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>

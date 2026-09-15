<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">

    <title>Dienstplan</title>

    <style>
        @page {
            margin: 18mm 12mm 16mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1e293b;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 4px 0;
        }

        .meta {
            margin-bottom: 18px;
            color: #64748b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            display: table-header-group;
        }

        th {
            background: #f1f5f9;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            padding: 7px 8px;
            border-bottom: 1px solid #cbd5e1;
        }

        td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .date {
            white-space: nowrap;
            width: 90px;
        }

        .weekday {
            width: 90px;
        }

        .service {
            width: 160px;
        }

        .helper {
            min-width: 150px;
        }

        .footer {
            position: fixed;
            bottom: -8mm;
            left: 0;
            right: 0;
            text-align: center;
            color: #94a3b8;
            font-size: 8px;
        }
    </style>
</head>

<body>

<h1>Dienstplan</h1>

<div class="meta">
    Zeitraum:
    {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}
    –
    {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
    <br>

    Wochentag: {{ $weekdayName }}
</div>

<table>

    <thead>
    <tr>
        <th class="date">Datum</th>
        <th class="weekday">Wochentag</th>
        <th class="service">Dienst</th>
        <th class="helper">Helfer 1</th>
        <th class="helper">Helfer 2</th>
        <th class="helper">Helfer 3</th>
        <th class="helper">Helfer 4</th>
    </tr>
    </thead>

    <tbody>

    @foreach($events as $event)

        @php
            $assignments = $event->assignments
                ->sortBy('slot_no')
                ->values();
        @endphp

        <tr>

            <td class="date">
                {{ $event->duty_date->format('d.m.Y') }}
            </td>

            <td class="weekday">
                {{ $event->duty_date
                    ->locale('de')
                    ->translatedFormat('l') }}
            </td>

            <td class="service">
                {{ $event->duty_name }}
            </td>

            @for($slot = 0; $slot < 4; $slot++)

                @php
                    $assignment = $assignments->get($slot);

                    $helperName = '';

                    if ($assignment?->member) {
                        $helperName = $assignment->member->full_name;
                    } elseif ($assignment?->externalContact) {
                        $helperName = $assignment->externalContact->full_name;
                    }
                @endphp

                <td class="helper">
                    {{ $helperName }}
                </td>

            @endfor

        </tr>

    @endforeach

    </tbody>

</table>

<div class="footer">
    Erstellt am {{ now()->format('d.m.Y H:i') }}
</div>

</body>
</html>

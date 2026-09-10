<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 15mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #111827;
        }

        h1 {
            margin: 0 0 4mm 0;
            font-size: 16pt;
        }

        .meta {
            margin-bottom: 6mm;
            color: #6b7280;
            font-size: 8.5pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            font-size: 8pt;
            padding: 2.5mm;
            border-bottom: 1px solid #d1d5db;
        }

        td {
            padding: 2.5mm;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .footer-total {
            margin-top: 6mm;
            text-align: right;
            font-size: 11pt;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }
    </style>
</head>

<body>

<h1>
    Offene Mitgliedsbeiträge {{ $year->year }}
</h1>

<div class="meta">
    Stand: {{ now()->format('d.m.Y') }}
    &nbsp;|&nbsp;
    Anzahl offene Beiträge: {{ $entries->count() }}
</div>

<table>
    <thead>
    <tr>
        <th>Mitglied</th>
        <th>Betrag</th>
        <th>Fällig</th>
        <th>Vorschreibung</th>
        <th>Letzte Erinnerung</th>
    </tr>
    </thead>

    <tbody>
    @foreach($entries as $entry)
        @php
            $prescription = $entry->prescriptions
                ->where('type', 'prescription')
                ->sortByDesc('sent_at')
                ->first();

            $reminder = $entry->prescriptions
                ->where('type', 'reminder')
                ->sortByDesc('sent_at')
                ->first();
        @endphp

        <tr>
            <td>
                {{ $entry->member->surname }}
                {{ $entry->member->name }}
            </td>

            <td class="amount">
                {{ number_format(
                    (float) ($entry->amount ?? 0),
                    2,
                    ',',
                    '.'
                ) }} €
            </td>

            <td>
                {{ $year->due_date
                    ? $year->due_date->format('d.m.Y')
                    : '—'
                }}
            </td>

            <td>
                @if($prescription)
                    {{ $prescription->sent_at?->format('d.m.Y') }}
                @else
                    <span class="muted">nicht versendet</span>
                @endif
            </td>

            <td>
                @if($reminder)
                    {{ $reminder->reminder_level }}. Erinnerung<br>
                    <span class="muted">
                            {{ $reminder->sent_at?->format('d.m.Y') }}
                        </span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="footer-total">
    Offener Gesamtbetrag:
    {{ number_format($openAmount, 2, ',', '.') }} €
</div>

</body>
</html>

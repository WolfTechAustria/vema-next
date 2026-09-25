@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.') . ' €';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 15mm 15mm 18mm 15mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #111827;
        }

        h1 {
            margin: 0 0 1mm 0;
            font-size: 16pt;
        }

        h2 {
            margin: 7mm 0 2mm 0;
            font-size: 11pt;
        }

        .meta {
            margin-bottom: 5mm;
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
            font-size: 7.5pt;
            padding: 2mm;
            border-bottom: 1px solid #d1d5db;
        }

        td {
            padding: 1.8mm 2mm;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
        }

        .income {
            color: #047857;
        }

        .expense {
            color: #be123c;
        }

        .muted {
            color: #6b7280;
        }

        .summary td {
            border-bottom: none;
            padding: 1.2mm 2mm;
        }

        .summary .total td {
            border-top: 1.5px solid #111827;
            font-weight: bold;
            font-size: 10pt;
        }

        .columns td {
            border-bottom: none;
            padding: 0;
            vertical-align: top;
        }

        .signatures {
            margin-top: 18mm;
        }

        .signatures td {
            border-bottom: none;
            width: 50%;
            padding: 0 6mm 0 0;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 1.5mm;
            font-size: 8pt;
            color: #374151;
        }
    </style>
</head>

<body>

<h1>Kassabericht Vereinsjahr {{ $year->name }}</h1>

<div class="meta">
    {{ $settings->name }}
    &nbsp;|&nbsp;
    Zeitraum {{ $year->start_date->format('d.m.Y') }} – {{ $year->end_date->format('d.m.Y') }}
    &nbsp;|&nbsp;
    @if($year->isClosed())
        Abgeschlossen am {{ $year->closed_at->format('d.m.Y') }}{{ $year->general_meeting_date ? ' (JHV ' . $year->general_meeting_date->format('d.m.Y') . ')' : '' }}
    @else
        Vorläufig, Stand {{ now()->format('d.m.Y') }}
    @endif
</div>

<table class="summary" style="width: 60%;">
    <tr>
        <td>Anfangsbestand</td>
        <td class="amount">{{ $money($year->opening_balance) }}</td>
    </tr>
    <tr>
        <td>+ Einnahmen</td>
        <td class="amount income">{{ $money($totals['income']) }}</td>
    </tr>
    <tr>
        <td>− Ausgaben</td>
        <td class="amount expense">{{ $money($totals['expense']) }}</td>
    </tr>
    <tr class="total">
        <td>{{ $year->isClosed() ? 'Endbestand' : 'Aktueller Kassastand' }}</td>
        <td class="amount">{{ $money($year->closing_balance ?? $closingBalance) }}</td>
    </tr>
</table>

<h2>Übersicht nach Kategorien</h2>

<table class="columns">
    <tr>
        @foreach(['income' => 'Einnahmen', 'expense' => 'Ausgaben'] as $type => $label)
            <td style="width: 50%; {{ $type === 'income' ? 'padding-right: 4mm;' : 'padding-left: 4mm;' }}">
                <table>
                    <thead>
                    <tr>
                        <th>{{ $label }}</th>
                        <th class="amount">Betrag</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($categorySummary->get($type, collect()) as $category => $sum)
                        <tr>
                            <td>{{ $category }}</td>
                            <td class="amount">{{ $money($sum) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="muted">Keine {{ $label }}</td>
                        </tr>
                    @endforelse
                    <tr>
                        <td><strong>Summe</strong></td>
                        <td class="amount"><strong>{{ $money($totals[$type]) }}</strong></td>
                    </tr>
                    </tbody>
                </table>
            </td>
        @endforeach
    </tr>
</table>

<h2>Buchungen</h2>

<table>
    <thead>
    <tr>
        <th>Nr.</th>
        <th>Datum</th>
        <th>Beschreibung</th>
        <th>Kategorie</th>
        <th class="amount">Einnahme</th>
        <th class="amount">Ausgabe</th>
        <th class="amount">Saldo</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td></td>
        <td>{{ $year->start_date->format('d.m.Y') }}</td>
        <td colspan="4" class="muted">Anfangsbestand</td>
        <td class="amount">{{ $money($year->opening_balance) }}</td>
    </tr>
    @forelse($rows as $row)
        @php($entry = $row['entry'])
        <tr>
            <td>{{ $entry->receipt_number }}</td>
            <td>{{ $entry->date->format('d.m.Y') }}</td>
            <td>
                {{ $entry->description }}
                @if($entry->attachments_count === 0)
                    <span class="muted">(ohne Beleg)</span>
                @endif
            </td>
            <td>{{ $entry->category ?? '' }}</td>
            <td class="amount income">{{ $entry->isIncome() ? $money($entry->amount) : '' }}</td>
            <td class="amount expense">{{ $entry->isIncome() ? '' : $money($entry->amount) }}</td>
            <td class="amount">{{ $money($row['balance']) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="muted">Keine Buchungen in diesem Vereinsjahr.</td>
        </tr>
    @endforelse
    </tbody>
</table>

@if($year->closing_note)
    <p class="muted" style="margin-top: 5mm;">Anmerkung: {{ $year->closing_note }}</p>
@endif

<table class="signatures">
    <tr>
        <td><div class="signature-line">Kassier</div></td>
        <td><div class="signature-line">Kassaprüfer</div></td>
    </tr>
</table>

</body>
</html>

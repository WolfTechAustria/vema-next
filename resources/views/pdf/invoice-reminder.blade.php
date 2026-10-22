<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $levelLabel }} zu Rechnung {{ $invoice->invoice_number }}
    </title>

    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;

            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.35;

            color: #111;
        }

        .content {
            margin-left: 15mm;
            margin-right: 15mm;

            padding-top: 55mm;
        }

        .recipient {
            min-height: 27mm;

            font-size: 9pt;
            line-height: 1.25;
        }

        .date {
            text-align: right;
            margin-bottom: 4mm;
        }

        h1 {
            font-size: 13pt;
            margin: 0 0 4mm 0;
        }

        p {
            margin: 0 0 3mm 0;
        }

        table.facts {
            border-collapse: collapse;
            margin: 4mm 0 5mm 0;
        }

        table.facts td {
            padding: 1mm 6mm 1mm 0;
            font-size: 9pt;
        }

        table.facts td.label {
            color: #555;
        }

        table.facts tr.total td {
            border-top: 0.5mm solid #111;
            font-weight: bold;
            font-size: 10pt;
        }

        .bank {
            width: 100%;
            margin-top: 6mm;
            border-collapse: collapse;
        }

        .bank td {
            width: 33.33%;
            vertical-align: top;
            padding-right: 3mm;
            font-size: 8.5pt;
        }
    </style>
</head>

<body>

@php
    $texts = [
        1 => [
            'Möglicherweise ist die Begleichung der unten genannten Rechnung in Vergessenheit geraten. Wir bitten Sie daher freundlich, den offenen Betrag zu überweisen.',
            'Sollten Sie den Betrag inzwischen bereits überwiesen haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.',
        ],
        2 => [
            'Trotz unserer Zahlungserinnerung konnten wir zu der unten genannten Rechnung leider noch keinen Zahlungseingang feststellen.',
            'Wir ersuchen Sie, den offenen Betrag bis zum angegebenen Datum zu überweisen. Sollten Sie bereits bezahlt haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.',
        ],
        3 => [
            'Die unten genannte Rechnung ist trotz mehrfacher Aufforderung weiterhin offen.',
            'Wir fordern Sie letztmalig auf, den offenen Betrag bis zum angegebenen Datum zu überweisen. Andernfalls behalten wir uns weitere Schritte vor.',
        ],
    ];
@endphp

<div class="content">

    <div class="recipient">
        @if($recipient->company_name)
            {{ $recipient->company_name }}<br>
        @endif

        @if($recipient->surname || $recipient->name)
            {{ trim($recipient->surname . ' ' . $recipient->name) }}<br>
        @endif

        @if($recipient->street)
            {{ $recipient->street }}<br>
        @endif

        {{ trim($recipient->zip . ' ' . $recipient->city) }}
    </div>

    <div class="date">{{ $settings->city ? $settings->city.', ' : '' }}{{ now()->format('d.m.Y') }}</div>

    <h1>{{ $levelLabel }} zu Rechnung {{ $invoice->invoice_number }}</h1>

    <p>Sehr geehrte Damen und Herren,</p>

    @foreach($texts[$level] as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    <table class="facts">
        <tr>
            <td class="label">Rechnungsnummer</td>
            <td>{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td class="label">Rechnungsdatum</td>
            <td>{{ $invoice->invoice_date->format('d.m.Y') }}</td>
        </tr>
        @if($invoice->due_date)
            <tr>
                <td class="label">Ursprünglich fällig am</td>
                <td>{{ $invoice->due_date->format('d.m.Y') }}</td>
            </tr>
        @endif
        @if($invoice->purpose)
            <tr>
                <td class="label">Anlass</td>
                <td>{{ $invoice->purpose }}</td>
            </tr>
        @endif
        <tr class="total">
            <td class="label">Offener Betrag</td>
            <td>{{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €</td>
        </tr>
        <tr>
            <td class="label">Bitte zahlen bis</td>
            <td>{{ $paymentDeadline->format('d.m.Y') }}</td>
        </tr>
    </table>

    <p>Mit freundlichen Grüßen<br>{{ $settings->name }}</p>

    <table class="bank">
        <tr>
            @if($settings->bank_name)
                <td>
                    Bank<br>
                    {{ $settings->bank_name }}
                </td>
            @endif

            @if($settings->iban)
                <td>
                    IBAN<br>
                    {{ $settings->iban }}
                </td>
            @endif

            @if($settings->bic)
                <td>
                    BIC<br>
                    {{ $settings->bic }}
                </td>
            @endif
        </tr>
    </table>

</div>

</body>

</html>

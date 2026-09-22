<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        Rechnung {{ $invoice->invoice_number }}
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
            line-height: 1.3;

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

        .meta {
            width: 100%;
            border-collapse: collapse;

            margin-top: 6mm;
            margin-bottom: 6mm;
        }

        .meta td {
            padding: 1mm 0;
            font-size: 9pt;
        }

        .meta td.label {
            width: 35mm;
            color: #555;
        }

        h1 {
            font-size: 13pt;
            margin: 0 0 4mm 0;
        }

        .intro, .footer-text {
            font-size: 9pt;
            margin-bottom: 4mm;
            white-space: pre-line;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;

            margin-top: 4mm;
            margin-bottom: 4mm;
        }

        table.items th {
            text-align: left;
            border-bottom: 0.5mm solid #111;

            padding: 1.5mm 2mm;
            font-size: 8.5pt;
        }

        table.items td {
            border-bottom: 0.25mm solid #ddd;

            padding: 1.5mm 2mm;
            font-size: 8.5pt;
            vertical-align: top;
        }

        table.items th.num, table.items td.num {
            text-align: right;
            white-space: nowrap;
        }

        table.totals {
            width: 70mm;
            margin-left: auto;
            border-collapse: collapse;

            margin-top: 2mm;
        }

        table.totals td {
            padding: 1mm 2mm;
            font-size: 9pt;
        }

        table.totals td.num {
            text-align: right;
            white-space: nowrap;
        }

        table.totals tr.total td {
            border-top: 0.5mm solid #111;
            font-weight: bold;
            font-size: 10pt;
        }

        .vat-note {
            margin-top: 3mm;
            font-size: 8pt;
            color: #555;
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

<div class="content">

    {{-- Empfänger --}}
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

    <h1>Rechnung {{ $invoice->invoice_number }}</h1>

    <table class="meta">
        <tr>
            <td class="label">Rechnungsdatum</td>
            <td>{{ $invoice->invoice_date->format('d.m.Y') }}</td>
        </tr>

        @if($invoice->due_date)
            <tr>
                <td class="label">Fällig bis</td>
                <td>{{ $invoice->due_date->format('d.m.Y') }}</td>
            </tr>
        @endif

        @if($invoice->purpose)
            <tr>
                <td class="label">Anlass</td>
                <td>{{ $invoice->purpose }}</td>
            </tr>
        @endif

        @if($recipient->vat_id)
            <tr>
                <td class="label">UID-Nummer</td>
                <td>{{ $recipient->vat_id }}</td>
            </tr>
        @endif
    </table>

    @if($invoice->intro_text)
        <div class="intro">{{ $invoice->intro_text }}</div>
    @endif

    <table class="items">
        <thead>
        <tr>
            <th>Bezeichnung</th>
            <th class="num">Menge</th>
            <th class="num">Einzelpreis</th>
            <th class="num">Rabatt</th>
            <th class="num">USt.</th>
            <th class="num">Gesamt</th>
        </tr>
        </thead>

        <tbody>
        @foreach($items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="num">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                <td class="num">{{ number_format((float) $item->price_net, 2, ',', '.') }} €</td>
                <td class="num">
                    @if((float) $item->discount_percent > 0)
                        {{ number_format((float) $item->discount_percent, 2, ',', '.') }} %
                    @else
                        –
                    @endif
                </td>
                <td class="num">
                    @if($invoice->small_business_no_vat)
                        –
                    @else
                        {{ number_format((float) $item->tax_rate, 2, ',', '.') }} %
                    @endif
                </td>
                <td class="num">{{ number_format((float) $item->line_total_gross, 2, ',', '.') }} €</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Zwischensumme netto</td>
            <td class="num">{{ number_format((float) $invoice->subtotal_net, 2, ',', '.') }} €</td>
        </tr>

        @if((float) $invoice->discount_total > 0)
            <tr>
                <td>Rabatt</td>
                <td class="num">- {{ number_format((float) $invoice->discount_total, 2, ',', '.') }} €</td>
            </tr>
        @endif

        @if(!$invoice->small_business_no_vat)
            <tr>
                <td>USt.</td>
                <td class="num">{{ number_format((float) $invoice->tax_total, 2, ',', '.') }} €</td>
            </tr>
        @endif

        <tr class="total">
            <td>Gesamtbetrag</td>
            <td class="num">{{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €</td>
        </tr>
    </table>

    @if($invoice->small_business_no_vat)
        <div class="vat-note">
            Kleinunternehmer gemäß § 6 Abs. 1 Z 27 UStG. Keine Umsatzsteuer ausgewiesen.
        </div>
    @endif

    @if($invoice->footer_text)
        <div class="footer-text" style="margin-top: 6mm;">{{ $invoice->footer_text }}</div>
    @endif

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

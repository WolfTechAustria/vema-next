<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        Mitgliedsbeitragsvorschreibung {{ $year->year }}
    </title>

    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;

            font-family: DejaVu Sans, sans-serif;
            font-size: 8.7pt;
            line-height: 1.18;

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

        .subject-row {
            width: 100%;

            margin-top: 1mm;
            margin-bottom: 4mm;
        }

        .salutation {
            margin-top: 2mm;
            margin-bottom: 3mm;

            text-align: center;

            color: #d00000;

            font-weight: bold;
            text-decoration: underline;
        }

        .body-text {
            font-size: 8.7pt;
            line-height: 1.18;
        }

        .body-text p {
            margin: 0 0 2.2mm 0;
        }

        .important {
            color: #d00000;
        }

        .termination-note {
            color: #178317;
        }

        .bank {
            width: 100%;

            margin-top: 3mm;

            border-collapse: collapse;
        }

        .bank td {
            width: 33.33%;
            vertical-align: top;

            padding-right: 3mm;

            font-size: 8.5pt;
        }

        .signatures {
            width: 100%;

            margin-top: 3mm;

            border-collapse: collapse;
        }

        .signatures td {
            width: 50%;
            vertical-align: top;

            font-size: 8.5pt;
        }

        .signature-image {
            height: 11mm;

            margin-top: 1mm;
            margin-bottom: 1mm;
        }
    </style>
</head>

<body>


{{-- Loch-/Falzmarken --}}
<div class="mark mark-1"></div>
<div class="mark mark-2"></div>
<div class="mark mark-3"></div>


<div class="content">

    {{-- Empfänger --}}
    <div class="recipient">

        {{ $member->name }} {{ $member->surname }}<br>

        {{ $member->street }}<br>

        {{ $member->zip }}
        {{ $member->city?->city }}

        @if($email)
            <br>
            E-Mail: {{ $email }}
        @endif

    </div>


    {{-- Betreff + Datum --}}
    <div class="subject-row">

        <div class="subject">
            Betrifft:
            <strong>
                Mitgliedsbeitrag {{ $year->year }}
            </strong>
        </div>

        <div class="date">
            Angerberg, am {{ now()->format('d.m.Y') }}
        </div>

        <div class="clearfix"></div>

    </div>


    {{-- persönliche Anrede --}}
    <div class="salutation">

        Geschätztes Mitglied der Schützengilde Angerberg,
        {{ $salutation }} {{ $member->name }}!

    </div>


    <div class="body-text">
        {!! $body !!}
    </div>

    {{-- Unterschriften --}}
    <table class="signatures">

        <tr>

            <td>

                Der Oberschützenmeister<br>

                <img
                    class="signature-image"
                    src="{{ public_path('images/letterhead/signatureOSM.JPG') }}"
                >

                <br>

                (OBERHAUSER Wolfgang)

            </td>


            <td class="signature-right">

                Der Schriftführer<br>

                <img
                    class="signature-image"
                    src="{{ public_path('images/letterhead/signatureSF.JPG') }}"
                >

                <br>

                (OBRIST Wolfgang)

            </td>

        </tr>

    </table>

</div>

</body>

</html>

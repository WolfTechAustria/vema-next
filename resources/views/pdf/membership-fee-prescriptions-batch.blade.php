<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

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
        }

        .page {
            position: relative;

            width: 210mm;
            height: 297mm;

            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .content {
            margin-left: 15mm;
            margin-right: 15mm;

            padding-top: 48mm;
        }

        .recipient {
            min-height: 27mm;
            padding-top: 7mm;

            font-size: 9pt;
            line-height: 1.25;
        }

        .subject-row {
            width: 100%;
            margin-top: 1mm;
            margin-bottom: 4mm;
        }

        .subject {
            float: left;
            width: 50%;
        }

        .date {
            float: right;
            width: 50%;
            text-align: right;
        }

        .clearfix {
            clear: both;
        }

        .body-text {
            font-size: 8.7pt;
            line-height: 1.18;
        }

        .body-text p {
            margin: 0 0 2.2mm 0;
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

        .signature-right {
            text-align: right;
        }

        .signature-image {
            height: 11mm;
        }
    </style>
</head>

<body>

@foreach($pages as $page)

    <div class="page">

        <div class="content">

            <div class="recipient">

                {{ $page['member']->name }}
                {{ $page['member']->surname }}<br>

                {{ $page['member']->street }}<br>

                {{ $page['member']->zip }}
                {{ $page['member']->city?->city }}

                @if($page['email'])
                    <br>
                    E-Mail: {{ $page['email'] }}
                @endif

            </div>

            <div class="subject-row">

                <div class="subject">
                    Betrifft:
                    <strong>
                        Mitgliedsbeitrag
                        {{ $page['year']->year }}
                    </strong>
                </div>

                <div class="date">
                    {{ \App\Models\Setting::current()->letterDateLine() }}
                </div>

                <div class="clearfix"></div>

            </div>

            <div class="body-text">
                {!! $page['body'] !!}
            </div>

            {{-- Unterschriften --}}
            @include('pdf.partials.signatures', ['rightAlignSecond' => true])

        </div>

    </div>

@endforeach

</body>
</html>

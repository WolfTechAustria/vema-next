<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $circular->title }}
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

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
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
            margin-top: 1mm;
            margin-bottom: 4mm;
        }

        .subject {
            font-size: 9pt;
        }

        .date {
            margin-top: 2mm;
            font-size: 8.5pt;
        }

        .body-text {
            font-size: 8.7pt;
            line-height: 1.18;
        }

        .body-text p {
            margin: 0 0 2.2mm 0;
        }

        .body-text ul,
        .body-text ol {
            margin-top: 1mm;
            margin-bottom: 2.2mm;
        }

        .signatures {
            width: 100%;
            margin-top: 6mm;
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

@foreach($pages as $page)

    <div class="page">

        <div class="content">

            <div class="recipient">

                {{ $page['member']->name }}
                {{ $page['member']->surname }}
                <br>

                {{ $page['member']->street }}
                <br>

                {{ $page['member']->zip }}
                {{ $page['member']->city?->city }}

            </div>

            <div class="subject-row">

                @if($circular->subject)
                    <div class="subject">
                        Betrifft:
                        <strong>
                            {{ $circular->subject }}
                        </strong>
                    </div>
                @endif

                <div class="date">
                    {{ \App\Models\Setting::current()->letterDateLine() }}
                </div>

            </div>

            <div class="body-text">
                {!! $page['body'] !!}
            </div>

            @include('pdf.partials.signatures')

        </div>

    </div>

@endforeach

</body>

</html>

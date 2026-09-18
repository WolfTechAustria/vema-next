<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        Geburtstagsliste {{ $monthName }}
    </title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background: #f8fafc;
    font-family: Arial, Helvetica, sans-serif;
    color: #111827;
">

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="background: #f8fafc; padding: 24px 12px;"
>
    <tr>
        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    max-width: 700px;
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                "
            >

                <tr>
                    <td style="padding: 28px 32px;">

                        <div style="
                            margin-bottom: 20px;
                            font-size: 18px;
                            font-weight: 700;
                        ">
                            🎂 Geburtstagsliste {{ $monthName }}
                        </div>

                        <div style="font-size: 15px; line-height: 1.6;">
                            <p>
                                Im Anhang findet ihr die Geburtstagsliste
                                der aktiven Mitglieder für
                                <strong>{{ $monthName }}</strong>
                                ({{ $memberCount }}
                                {{ $memberCount === 1 ? 'Mitglied' : 'Mitglieder' }}).
                            </p>
                        </div>

                    </td>
                </tr>

                <tr>
                    <td style="padding: 18px 32px;">
                        @include('emails.partials.signature')
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>

</html>

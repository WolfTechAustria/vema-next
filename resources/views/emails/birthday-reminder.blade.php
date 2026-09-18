<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        Geburtstags-Erinnerung: {{ $member->full_name }}
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
                            🎉 Geburtstags-Erinnerung
                        </div>

                        <div style="font-size: 15px; line-height: 1.6;">
                            <p>
                                <strong>{{ $member->full_name }}</strong>
                                hat demnächst
                                {{ $isRound ? 'einen runden' : 'einen halbrunden' }}
                                Geburtstag und wird
                                <strong>{{ $age }} Jahre</strong> alt.
                            </p>

                            @if ($member->dateOfBirth)
                                <p>
                                    Geburtsdatum:
                                    {{ $member->dateOfBirth->format('d.m.Y') }}
                                </p>
                            @endif

                            <p style="margin-top: 24px;">
                                <a
                                    href="{{ route('members.show', $member) }}"
                                    style="
                                    display: inline-block;
                                    padding: 10px 18px;
                                    background: #2563eb;
                                    color: #ffffff;
                                    text-decoration: none;
                                    border-radius: 6px;
                                    font-weight: 600;
                                    "
                                >
                                    Mitglied ansehen
                                </a>
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

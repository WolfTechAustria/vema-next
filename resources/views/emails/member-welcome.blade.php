<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $subjectLine }}
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
                    max-width: 620px;
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                "
            >

                <tr>
                    <td style="padding: 28px 32px;">

                        <div style="font-size: 15px; line-height: 1.6;">
                            {!! $bodyHtml !!}
                        </div>

                        @if($portalUrl)
                            <div style="
                                margin-top: 24px;
                                padding: 18px 20px;
                                background: #f8fafc;
                                border: 1px solid #e5e7eb;
                                border-radius: 8px;
                            ">
                                <p style="margin: 0 0 8px 0; font-size: 15px; font-weight: bold;">
                                    Dein Mitgliederbereich
                                </p>

                                <p style="margin: 0 0 14px 0; font-size: 14px; line-height: 1.6; color: #334155;">
                                    Im Mitgliederbereich siehst du deine Beiträge, Rundschreiben,
                                    Termine und Dienste und kannst deine Kontaktdaten selbst aktualisieren.
                                    Ein Passwort brauchst du nicht: Gib einfach diese E-Mail-Adresse ein
                                    und du bekommst einen Login-Link zugeschickt.
                                </p>

                                <a
                                    href="{{ $portalUrl }}"
                                    style="
                                        display: inline-block;
                                        background: #0f172a;
                                        color: #ffffff;
                                        text-decoration: none;
                                        padding: 12px 18px;
                                        border-radius: 8px;
                                        font-size: 14px;
                                        font-weight: bold;
                                    "
                                >
                                    Zum Mitgliederbereich
                                </a>
                            </div>
                        @endif

                        @if($whatsappUrl)
                            <div style="
                                margin-top: 16px;
                                padding: 18px 20px;
                                background: #f0fdf4;
                                border: 1px solid #bbf7d0;
                                border-radius: 8px;
                            ">
                                <p style="margin: 0 0 8px 0; font-size: 15px; font-weight: bold;">
                                    WhatsApp-Community
                                </p>

                                <p style="margin: 0 0 14px 0; font-size: 14px; line-height: 1.6; color: #334155;">
                                    Aktuelles aus dem Verein gibt es auch in unserer WhatsApp-Community.
                                    Öffne den Link am besten direkt am Handy.
                                </p>

                                <a
                                    href="{{ $whatsappUrl }}"
                                    style="
                                        display: inline-block;
                                        background: #16a34a;
                                        color: #ffffff;
                                        text-decoration: none;
                                        padding: 12px 18px;
                                        border-radius: 8px;
                                        font-size: 14px;
                                        font-weight: bold;
                                    "
                                >
                                    WhatsApp-Community beitreten
                                </a>
                            </div>
                        @endif

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

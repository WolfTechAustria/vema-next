<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $heading }}
    </title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f8fafc;
    font-family:Arial, Helvetica, sans-serif;
    color:#111827;
">

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="background:#f8fafc; padding:24px 12px;"
>
    <tr>
        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                border="0"
                style="
                    max-width:620px;
                    background:#ffffff;
                    border:1px solid #e5e7eb;
                    border-radius:10px;
                "
            >

                <tr>
                    <td style="padding:28px 32px;">

                        <h2 style="margin:0 0 18px 0; font-size:20px;">
                            {{ $heading }}
                        </h2>

                        <p style="margin:0 0 14px 0; font-size:15px; line-height:1.6;">
                            Danke! Ihr Beitrag <strong>„{{ $post->title }}“</strong> ist für die Homepage von {{ $clubName }} eingegangen
                            und liegt jetzt als <strong>Entwurf</strong> in VEMA
                            ({{ $imageCount }} {{ $imageCount === 1 ? 'Bild' : 'Bilder' }}, {{ $attachmentCount }} {{ $attachmentCount === 1 ? 'PDF' : 'PDFs' }}).
                        </p>

                        <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                            Er erscheint auf der Homepage, sobald ihn ein Homepage-Verantwortlicher geprüft und veröffentlicht hat.
                        </p>

                        <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px 0;">
                            <tr>
                                <td style="border-radius:8px; background:#111827;">
                                    <a href="{{ $editUrl }}" style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                        Beitrag in VEMA öffnen
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0; font-size:13px; line-height:1.6; color:#6b7280;">
                            Diese Nachricht wurde automatisch versendet.
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>

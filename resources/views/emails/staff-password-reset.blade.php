<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        {{ $isNewAccount ? 'Zugang zu VEMA' : 'Passwort zurücksetzen' }}
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
                            {{ $isNewAccount ? 'Zugang zu VEMA' : 'Passwort zurücksetzen' }}
                        </h2>

                        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                            Hallo {{ $user->username }},
                        </p>

                        @if($isNewAccount)
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                                für dich wurde ein Zugang zu VEMA (Vereinsmanagement) mit dem
                                Benutzernamen <strong>{{ $user->username }}</strong> angelegt.
                                Über den folgenden Link kannst du dein Passwort festlegen.
                            </p>
                        @else
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                                für dein Konto wurde ein neues Passwort angefordert. Über den
                                folgenden Link kannst du ein neues Passwort festlegen.
                            </p>
                        @endif

                        <p style="margin:0 0 20px 0;">
                            <a
                                href="{{ $resetUrl }}"
                                style="
                                    display:inline-block;
                                    background:#0f172a;
                                    color:#ffffff;
                                    text-decoration:none;
                                    padding:12px 18px;
                                    border-radius:8px;
                                    font-size:14px;
                                    font-weight:bold;
                                "
                            >
                                Passwort festlegen
                            </a>
                        </p>

                        <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
                            Der Link ist 60 Minuten gültig. Falls du diese Anfrage nicht
                            gestellt hast, kannst du diese E-Mail ignorieren.
                        </p>

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

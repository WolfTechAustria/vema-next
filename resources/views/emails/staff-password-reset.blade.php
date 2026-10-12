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

                        @if($welcomeHtml)
                            <div style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                                {!! $welcomeHtml !!}
                            </div>

                            <table
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                style="margin:0 0 20px 0; font-size:14px; line-height:1.6; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;"
                            >
                                <tr>
                                    <td style="padding:14px 18px;">
                                        <strong>Deine Anmeldedaten</strong><br>
                                        Benutzername: <strong>{{ $user->username }}</strong><br>
                                        oder deine E-Mail-Adresse: <strong>{{ $user->email }}</strong><br>
                                        Login-Seite: <a href="{{ $loginUrl }}" style="color:#0f172a;">{{ $loginUrl }}</a>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                                Hallo {{ $user->username }},
                            </p>
                        @endif

                        @if($isNewAccount)
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                                Über den folgenden Link legst du dein Passwort fest:
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

                        @if($isNewAccount)
                            <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
                                Danach meldest du dich jederzeit hier an:
                            </p>

                            <p style="margin:0 0 20px 0;">
                                <a
                                    href="{{ $loginUrl }}"
                                    style="
                                        display:inline-block;
                                        background:#ffffff;
                                        color:#0f172a;
                                        text-decoration:none;
                                        padding:11px 17px;
                                        border:1px solid #cbd5e1;
                                        border-radius:8px;
                                        font-size:14px;
                                        font-weight:bold;
                                    "
                                >
                                    Zur Anmeldung
                                </a>
                            </p>

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
                                Der Link zum Festlegen des Passworts ist 60 Minuten gültig. Ist er
                                abgelaufen, nutze auf der Login-Seite „Passwort vergessen?“.
                            </p>
                        @else
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
                                Der Link ist 60 Minuten gültig. Falls du diese Anfrage nicht
                                gestellt hast, kannst du diese E-Mail ignorieren.
                            </p>
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

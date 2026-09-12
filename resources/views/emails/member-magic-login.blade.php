<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>
        Login zum Mitgliederbereich
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

                        <h2 style="
                            margin:0 0 18px 0;
                            font-size:20px;
                        ">
                            Mitgliederbereich
                        </h2>

                        <p style="
                            margin:0 0 16px 0;
                            font-size:15px;
                            line-height:1.6;
                        ">
                            Hallo {{ $member->name }},
                        </p>

                        <p style="
                            margin:0 0 20px 0;
                            font-size:15px;
                            line-height:1.6;
                        ">
                            über den folgenden Link kannst du dich einmalig
                            im Mitgliederbereich anmelden.
                        </p>

                        <p style="margin:0 0 20px 0;">
                            <a
                                href="{{ $loginUrl }}"
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
                                Im Mitgliederbereich anmelden
                            </a>
                        </p>

                        <p style="
                            margin:0;
                            font-size:13px;
                            line-height:1.6;
                            color:#64748b;
                        ">
                            Der Link ist 30 Minuten gültig und kann nur einmal verwendet werden.
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>

</html>

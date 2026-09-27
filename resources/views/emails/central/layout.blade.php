<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">

    <title>@yield('title')</title>
</head>

<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#111827;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f8fafc; padding:24px 12px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:620px; background:#ffffff; border:1px solid #e5e7eb; border-radius:10px;">
                <tr>
                    <td style="padding:28px 32px; font-size:15px; line-height:1.6;">

                        <h2 style="margin:0 0 18px 0; font-size:20px;">
                            @yield('title')
                        </h2>

                        @yield('content')

                        <p style="margin:28px 0 0 0; padding-top:16px; border-top:1px solid #e5e7eb; font-size:13px; color:#6b7280;">
                            VEMA · Vereinsverwaltung · {{ config('tenancy.tenant_domain') }}
                        </p>

                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>

</html>

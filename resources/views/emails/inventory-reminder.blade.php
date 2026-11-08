<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Material: fällige Prüfungen und Rückgaben</title>
</head>

<body style="margin: 0; padding: 0; background: #f8fafc; font-family: Arial, Helvetica, sans-serif; color: #111827;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #f8fafc; padding: 24px 12px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 700px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px;">
                <tr>
                    <td style="padding: 28px 32px; font-size: 15px; line-height: 1.6;">

                        <div style="margin-bottom: 20px; font-size: 18px; font-weight: 700;">
                            Materialverwaltung
                        </div>

                        @if($inspections->isNotEmpty())
                            <p style="margin: 0 0 8px 0; font-weight: 700;">Prüfung / Wartung fällig</p>

                            <ul style="margin: 0 0 20px 0; padding-left: 20px;">
                                @foreach($inspections as $item)
                                    <li>
                                        {{ $item->label() }}
                                        – {{ $item->next_inspection_on->format('d.m.Y') }}
                                        @if($item->isInspectionOverdue())
                                            <strong style="color: #b91c1c;">(überfällig)</strong>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($returns->isNotEmpty())
                            <p style="margin: 0 0 8px 0; font-weight: 700;">Rückgabe überfällig</p>

                            <ul style="margin: 0 0 20px 0; padding-left: 20px;">
                                @foreach($returns as $loan)
                                    <li>
                                        {{ $loan->item->label() }}
                                        – {{ $loan->member?->full_name ?? 'Mitglied #'.$loan->memberID }},
                                        Rückgabe bis {{ $loan->due_on->format('d.m.Y') }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <p style="margin: 0;">
                            <a href="{{ $inventoryUrl }}" style="display: inline-block; background: #0f172a; color: #ffffff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 700;">
                                Material in VEMA öffnen
                            </a>
                        </p>

                    </td>
                </tr>
            </table>

            <p style="margin: 16px 0 0 0; font-size: 12px; color: #6b7280;">
                Diese Erinnerung erhalten Administratoren und Zeugwarte des Vereins.
            </p>

        </td>
    </tr>
</table>

</body>
</html>

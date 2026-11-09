<table cellpadding="0" cellspacing="0" border="0" style="margin: 12px 0 20px 0; font-size: 14px;">
    @foreach(array_filter([
        'Name' => $application->full_name,
        'Geburtsdatum' => $application->dateOfBirth?->format('d.m.Y'),
        'Adresse' => trim(($application->street ? $application->street.', ' : '').$application->zip.' '.$application->city, ', '),
        'E-Mail' => $application->email,
        'Telefon' => $application->phone,
        'Mitgliedsart' => $application->membership_type,
        'Beitrittsdatum' => $application->applied_on->format('d.m.Y'),
    ]) as $label => $value)
        <tr>
            <td style="padding: 2px 16px 2px 0; color: #6b7280; vertical-align: top;">{{ $label }}</td>
            <td style="padding: 2px 0;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

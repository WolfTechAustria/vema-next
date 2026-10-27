<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">

    <title>Geburtstage im {{ $monthName }}</title>

    @include('pdf.partials.list-layout', ['part' => 'styles'])
</head>

<body>

@include('pdf.partials.list-layout', ['part' => 'logo'])

@include('pdf.partials.list-layout', [
    'part' => 'header',
    'listTitle' => 'Geburtstage im '.$monthName,
    'listSubtitle' => '(Stand: '.now()->format('d.m.Y').' – '.$members->count().' '.($members->count() === 1 ? 'Mitglied' : 'Mitglieder').')',
])

<table class="list-table full-width">
    @forelse($members as $row)
        @php
            $member = $row['member'];
            $dateOfBirth = \Carbon\Carbon::parse($member->dateOfBirth);
            $birthday = \Carbon\Carbon::create($year, $dateOfBirth->month, 1)->day(
                min($dateOfBirth->day, \Carbon\Carbon::create($year, $dateOfBirth->month, 1)->daysInMonth)
            );
        @endphp

        <tr @class(['highlight' => $row['isRound'] || $row['isHalfRound']])>
            <td class="nowrap" style="width: 8%;">{{ mb_strtoupper($birthday->locale('de')->isoFormat('dd')) }}</td>

            <td class="nowrap" style="width: 10%;">{{ $dateOfBirth->format('d.m.') }}</td>

            <td style="width: 45%;">{{ $member->full_name }}</td>

            <td class="nowrap" style="width: 12%;">{{ $row['age'] }} Jahre</td>

            <td>
                @if($row['isRound'])
                    runder Geburtstag
                @elseif($row['isHalfRound'])
                    halbrunder Geburtstag
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td>Keine Geburtstage aktiver Mitglieder in diesem Monat.</td>
        </tr>
    @endforelse
</table>

</body>
</html>

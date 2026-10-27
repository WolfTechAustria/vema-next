<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">

    <title>{{ $title }}</title>

    @include('pdf.partials.list-layout', ['part' => 'styles'])

    <style>
        .separator {
            padding-right: 2mm;
        }
    </style>
</head>

<body>

@include('pdf.partials.list-layout', ['part' => 'logo'])

@include('pdf.partials.list-layout', [
    'part' => 'header',
    'listTitle' => $title,
    'listSubtitle' => filled($note)
        ? '('.$note.')'
        : \Carbon\Carbon::parse($dateFrom)->format('d.m.Y').' – '.\Carbon\Carbon::parse($dateTo)->format('d.m.Y'),
])

@php
    $showRoleColumn = $events->pluck('roleID')->unique()->count() > 1;

    /*
     * Bis 3 Helfer je eine Spalte mit „&“ (wie die Vorlage), ab 4 Helfern
     * alle Namen in einer umbrechenden Zelle, damit die Tabelle im Blatt bleibt.
     */
    $helpersInOneCell = $maxHelperColumns > 3;

    $eventsByDate = $events->groupBy(
        fn ($event) => $event->duty_date->toDateString()
    );
@endphp

<table @class(['list-table', 'full-width' => $helpersInOneCell])>

    @foreach($eventsByDate as $eventsOfDate)

        @foreach($eventsOfDate as $index => $event)

            @php
                $helperNames = $event->assignments
                    ->sortBy('slot_no')
                    ->map(fn ($assignment) => $assignment->member?->full_name ?? $assignment->externalContact?->full_name)
                    ->filter()
                    ->values();
            @endphp

            <tr>
                <td class="nowrap">
                    @if($index === 0)
                        {{ mb_strtoupper($event->duty_date->locale('de')->isoFormat('dd')) }}
                    @endif
                </td>

                <td class="nowrap">
                    @if($index === 0)
                        {{ $event->duty_date->format('d.m.Y') }}
                    @endif
                </td>

                @if($showRoleColumn)
                    <td class="nowrap">{{ $event->duty_name }}</td>
                @endif

                @if($helpersInOneCell)
                    <td style="width: 100%;">{{ $helperNames->join(', ', ' & ') }}</td>
                @else
                    @for($slot = 0; $slot < $maxHelperColumns; $slot++)
                        @if($slot > 0)
                            <td class="separator nowrap">{{ $helperNames->has($slot) ? '&' : '' }}</td>
                        @endif

                        <td class="nowrap">{{ $helperNames->get($slot) }}</td>
                    @endfor
                @endif
            </tr>

        @endforeach

    @endforeach

</table>

</body>
</html>

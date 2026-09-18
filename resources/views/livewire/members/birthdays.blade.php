<div class="space-y-4">

    <div class="flex items-center gap-3">
        <label for="month" class="text-sm font-medium text-gray-700">
            Monat:
        </label>

        <select
            wire:model.live="month"
            id="month"
            class="rounded-md border-gray-300 text-sm"
        >
            @foreach (range(1, 12) as $m)
                <option value="{{ $m }}">
                    {{ now()->setMonth($m)->translatedFormat('F') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900">
            Geburtstage im {{ $monthName }}
        </h2>


       <a href="{{ route('members.birthdays.pdf', ['month' => $month]) }}"
        target="_blank"
        class="inline-flex items-center gap-2 rounded-md bg-gray-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-800"
        >
        PDF drucken
        </a>
    </div>

    @if ($members->isEmpty())
        <p class="text-sm text-gray-500">
            Keine Geburtstage aktiver Mitglieder in diesem Monat.
        </p>
    @else
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
            <tr class="text-left text-gray-500">
                <th class="py-2 pr-4">Tag</th>
                <th class="py-2 pr-4">Name</th>
                <th class="py-2 pr-4">Wird</th>
                <th class="py-2 pr-4"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @foreach ($members as $row)
                <tr>
                    <td class="py-2 pr-4">
                        {{ $row['day'] }}.
                    </td>
                    <td class="py-2 pr-4">

                        <a
                        href="{{ route('members.show', $row['member']) }}"
                        class="text-blue-600 hover:underline"
                        >
                        {{ $row['member']->full_name }}
                        </a>
                    </td>
                    <td class="py-2 pr-4">
                        {{ $row['age'] }} Jahre
                    </td>
                    <td class="py-2 pr-4">
                        @if ($row['isRound'])
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                    🎉 rund
                                </span>
                        @elseif ($row['isHalfRound'])
                            <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">
                                    halbrund
                                </span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

</div>

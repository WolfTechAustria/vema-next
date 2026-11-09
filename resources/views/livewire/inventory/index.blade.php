<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Material</h2>

            <p class="mt-1 text-sm text-slate-500">
                Inventar des Vereins, Ausgabe an Mitglieder und Prüftermine.
            </p>
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                wire:click="export"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Excel
            </button>

            <a
                href="{{ route('inventory.scan') }}"
                wire:navigate
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Schnellausgabe
            </a>

            <a
                href="{{ route('inventory.create') }}"
                wire:navigate
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Material
            </a>
        </div>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col flex-wrap gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center">

            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Bezeichnung, Nummer, Lagerort oder Mitglied …"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 sm:max-w-sm"
            >

            <select wire:model.live="categoryFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Alle Kategorien</option>
                @foreach($categories as $category)
                    <option value="{{ $category->categoryID }}">{{ $category->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="conditionFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Im Bestand</option>
                @foreach($conditions as $condition)
                    <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="all">Alle</option>
                <option value="available">Verfügbar</option>
                <option value="issued">Ausgegeben</option>
                <option value="inspection_due">Prüfung fällig</option>
                <option value="overdue_return">Rückgabe überfällig</option>
            </select>

        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3"></th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nr.</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Bezeichnung</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Lagerort</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Zustand</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Ausgegeben an</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Nächste Prüfung</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                @forelse($items as $item)
                    <tr wire:key="item-{{ $item->itemID }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('inventory.show', $item) }}'">
                        <td class="w-14 px-4 py-2">
                            @if($photoUrl = $photos->url($item))
                                <img src="{{ $photoUrl }}" alt="" class="h-10 w-10 rounded-md object-cover">
                            @else
                                <div class="h-10 w-10 rounded-md bg-slate-100"></div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm text-slate-600">{{ $item->inventory_number ?? '–' }}</td>

                        <td class="px-4 py-3">
                            <a href="{{ route('inventory.show', $item) }}" wire:navigate class="font-medium text-slate-900 hover:underline">{{ $item->name }}</a>
                            @if($item->category || $item->code)
                                <div class="text-xs text-slate-500">
                                    {{ $item->category?->name }}
                                    @if($item->code)
                                        <span class="font-mono text-slate-400">{{ $item->category ? '· ' : '' }}{{ $item->code }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm text-slate-600">{{ $item->location ?? '–' }}</td>

                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $item->condition->badgeClasses() }}">
                                {{ $item->condition->label() }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-sm">
                            @if($item->currentLoan)
                                <span class="text-slate-700">{{ $item->currentLoan->member?->full_name ?? 'Mitglied #'.$item->currentLoan->memberID }}</span>
                                @if($item->currentLoan->isOverdue())
                                    <span class="ml-1 text-xs font-semibold text-red-700">Rückgabe überfällig</span>
                                @endif
                            @else
                                <span class="text-slate-400">verfügbar</span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm">
                            @if($item->next_inspection_on)
                                <span class="{{ $item->isInspectionOverdue() ? 'font-semibold text-red-700' : ($item->isInspectionDue() ? 'font-semibold text-amber-700' : 'text-slate-600') }}">
                                    {{ $item->next_inspection_on->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="text-slate-400">–</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">
                            Kein Material gefunden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </section>

</div>

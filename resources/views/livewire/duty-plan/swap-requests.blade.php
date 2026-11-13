<div class="space-y-6">

    @include('partials.flash-messages')

    {{-- Kopf --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Diensttausch
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Mitglieder können ihre Dienste im Mitgliederbereich tauschen oder an andere Helfer desselben Dienstplans abgeben.
            </p>
        </div>

        <a
            href="{{ route('duty-plan.index') }}"
            wire:navigate
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Zurück zum Dienstplan
        </a>
    </div>

    {{-- Wartet auf Freigabe --}}
    @if($requiresApproval || $awaiting->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">
                    Wartet auf Freigabe
                    @if($awaiting->isNotEmpty())
                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $awaiting->count() }}</span>
                    @endif
                </h3>
            </div>

            @forelse($awaiting as $swap)
                @php($warnings = $swap->acceptedBy ? $swaps->warningsForRequest($swap, $swap->acceptedBy) : [])
                <div wire:key="swap-awaiting-{{ $swap->swapID }}" class="border-b border-slate-100 px-6 py-4 last:border-b-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="text-sm text-slate-700">
                            <div>
                                <strong>{{ $swaps->describe($swap->assignment?->event) }}</strong>:
                                {{ $swap->requester?->full_name }} → {{ $swap->acceptedBy?->full_name }}
                            </div>
                            @if($swap->isSwap())
                                <div>
                                    <strong>{{ $swaps->describe($swap->targetAssignment?->event) }}</strong>:
                                    {{ $swap->acceptedBy?->full_name }} → {{ $swap->requester?->full_name }}
                                </div>
                            @endif
                            @if($swap->message)
                                <p class="mt-1 italic text-slate-500">„{{ $swap->message }}“</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="approve({{ $swap->swapID }})"
                                class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                Freigeben
                            </button>
                            <button
                                type="button"
                                wire:click="reject({{ $swap->swapID }})"
                                wire:confirm="Tausch ablehnen? Die Dienste bleiben unverändert."
                                class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Ablehnen
                            </button>
                        </div>
                    </div>

                    @if($warnings !== [])
                        <div class="mt-2 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            @foreach($warnings as $warning)
                                <div>{{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="px-6 py-4 text-sm text-slate-500">Keine Tausche warten auf eine Freigabe.</p>
            @endforelse
        </section>
    @endif

    {{-- Verlauf --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">Anfragen</h3>
            <p class="mt-1 text-sm text-slate-500">Die letzten 50 Tausch- und Abgabeanfragen.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Dienst</th>
                        <th class="px-6 py-3">Art</th>
                        <th class="px-6 py-3">Von</th>
                        <th class="px-6 py-3">An / übernommen von</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($history as $swap)
                        <tr wire:key="swap-history-{{ $swap->swapID }}">
                            <td class="px-6 py-3">
                                {{ $swaps->describe($swap->assignment?->event) }}
                                @if($swap->isSwap())
                                    <div class="text-xs text-slate-500">gegen {{ $swaps->describe($swap->targetAssignment?->event) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $swap->type->label() }}</td>
                            <td class="px-6 py-3">{{ $swap->requester?->full_name }}</td>
                            <td class="px-6 py-3">
                                {{ $swap->acceptedBy?->full_name ?? $swap->targetMember?->full_name ?? 'Alle im Dienstplan' }}
                            </td>
                            <td class="px-6 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $swap->status->badgeClasses() }}">
                                    {{ $swap->status->label() }}
                                </span>
                                <div class="mt-1 text-xs text-slate-400">{{ $swap->updated_at?->format('d.m.Y H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-slate-500">Noch keine Anfragen.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Einstellungen --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">Einstellungen</h3>
            @unless($isAdmin)
                <p class="mt-1 text-sm text-slate-500">Nur Administratoren können die Einstellungen ändern.</p>
            @endunless
        </div>

        <form wire:submit="saveSettings" class="space-y-5 p-6">
            <fieldset @disabled(! $isAdmin) class="space-y-5">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="enabled" class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="font-medium text-slate-800">Diensttausch im Mitgliederbereich erlauben</span>
                        <span class="block text-slate-500">Mitglieder können eigene Dienste tauschen oder an Helfer desselben Dienstplans abgeben.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="requiresApproval" class="mt-0.5 rounded border-slate-300">
                    <span>
                        <span class="font-medium text-slate-800">Tausch muss vom Verein freigegeben werden</span>
                        <span class="block text-slate-500">Ohne Freigabe wird der Tausch sofort nach der Annahme in den Dienstplan übernommen.</span>
                    </span>
                </label>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="swap-deadline" class="mb-1 block text-sm font-medium text-slate-700">Frist (Stunden vor Dienstbeginn)</label>
                        <input id="swap-deadline" type="number" min="0" max="720" wire:model="deadlineHours" class="w-full rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-xs text-slate-500">0 = bis zum Dienstbeginn. Offene Anfragen verfallen danach automatisch.</p>
                        @error('deadlineHours') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="swap-notify" class="mb-1 block text-sm font-medium text-slate-700">Benachrichtigung an</label>
                        <input id="swap-notify" type="text" wire:model="notifyEmails" placeholder="leer = alle Administratoren" class="w-full rounded-lg border-slate-300 text-sm">
                        <p class="mt-1 text-xs text-slate-500">Mehrere Adressen mit Komma trennen.</p>
                        @error('notifyEmails') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </fieldset>

            @if($isAdmin)
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                    Speichern
                </button>
            @endif
        </form>
    </section>

</div>

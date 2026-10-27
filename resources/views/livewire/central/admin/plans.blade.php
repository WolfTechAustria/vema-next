<div class="space-y-5">

    <div>
        <h1 class="text-2xl font-bold tracking-tight">Pakete</h1>
        <p class="mt-1 text-sm text-slate-500">
            Funktionen und Limits je Paket. Änderungen gelten sofort für alle Vereine mit diesem Paket
            (individuelle Anpassungen eines Vereins haben Vorrang). Während der Testphase gilt mindestens Verein Plus.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Funktion / Limit</th>
                        @foreach($plans as $plan)
                            <th class="px-3 py-3 text-center">
                                {{ $plan->label() }}
                                @if(in_array($plan->value, $customized, true))
                                    <div class="mt-1 font-normal normal-case tracking-normal">
                                        <span class="text-amber-700">angepasst</span> ·
                                        <button
                                            type="button"
                                            wire:click="resetPlan('{{ $plan->value }}')"
                                            wire:confirm="{{ $plan->label() }} auf die Werte der Preisliste zurücksetzen?"
                                            class="text-slate-500 underline hover:text-slate-800"
                                        >Standard</button>
                                    </div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr class="bg-slate-50/60">
                        <td colspan="{{ count($plans) + 1 }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Limits <span class="font-normal normal-case tracking-normal">(leer = unbegrenzt)</span>
                        </td>
                    </tr>

                    @foreach($limitCases as $limit)
                        <tr wire:key="limit-{{ $limit->value }}">
                            <td class="px-4 py-2 font-medium">{{ $limit->label() }}</td>
                            @foreach($plans as $plan)
                                <td class="px-3 py-2 text-center">
                                    <input
                                        type="number"
                                        min="1"
                                        wire:model="limits.{{ $plan->value }}.{{ $limit->value }}"
                                        placeholder="∞"
                                        class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm @error('limits.'.$plan->value.'.'.$limit->value) border-red-400 @enderror"
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="bg-slate-50/60">
                        <td colspan="{{ count($plans) + 1 }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Funktionen <span class="font-normal normal-case tracking-normal">(Mitglieder, Beiträge, Kassabuch und Rundschreiben hat jedes Paket)</span>
                        </td>
                    </tr>

                    @foreach($featureCases as $feature)
                        <tr wire:key="feature-{{ $feature->value }}">
                            <td class="px-4 py-2">{{ $feature->label() }}</td>
                            @foreach($plans as $plan)
                                <td class="px-3 py-2 text-center">
                                    <input
                                        type="checkbox"
                                        wire:model="features.{{ $plan->value }}.{{ $feature->value }}"
                                        class="h-4 w-4 rounded border-slate-300"
                                    >
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
            Speichern
        </button>
    </form>

</div>

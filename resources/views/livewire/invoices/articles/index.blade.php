<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.invoices-nav')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">
                Artikel
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Wiederverwendbare Positionen mit Standardpreis und USt.-Satz. Wird beim Erstellen einer Rechnung
                automatisch um neue Freitext-Positionen ergänzt.
            </p>
        </div>

        @unless($showForm)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white"
            >
                + Artikel erstellen
            </button>
        @endunless
    </div>

    @if($showForm)

        <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                    {{ $editingArticleID ? 'Artikel bearbeiten' : 'Neuer Artikel' }}
                </h3>

                <button type="button" wire:click="cancelEdit" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                    Abbrechen
                </button>
            </div>

            <div class="mt-5 space-y-4">

                <div>
                    <label class="mb-1 block text-sm font-medium">Bezeichnung</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Beschreibung</label>
                    <textarea wire:model="description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Einheit</label>
                        <input type="text" wire:model="unit" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Preis netto</label>
                        <input type="text" wire:model="price_net" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('price_net')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">USt. %</label>
                        <input type="text" wire:model="tax_rate" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('tax_rate')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model="active" class="h-4 w-4 rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Artikel aktiv</span>
                </label>

                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $editingArticleID ? 'Änderungen speichern' : 'Artikel anlegen' }}
                    </span>
                    <span wire:loading wire:target="save">Speichern …</span>
                </button>

            </div>

        </section>

    @endif

    {{-- Liste --}}
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 p-4">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Artikel suchen …"
                class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2"
            >
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Bezeichnung</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Preis netto</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">USt.</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500"></th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                @forelse($articles as $article)
                    <tr wire:key="article-{{ $article->articleID }}" class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium text-slate-900">{{ $article->name }}</td>
                        <td class="px-6 py-4 text-right text-slate-600">{{ number_format((float) $article->price_net, 2, ',', '.') }} € / {{ $article->unit }}</td>
                        <td class="px-6 py-4 text-right text-slate-600">{{ number_format((float) $article->tax_rate, 2, ',', '.') }} %</td>
                        <td class="px-6 py-4 text-center">
                            <button
                                type="button"
                                wire:click="toggleActive({{ $article->articleID }})"
                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $article->active ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-500' }}"
                            >
                                {{ $article->active ? 'Aktiv' : 'Inaktiv' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button type="button" wire:click="edit({{ $article->articleID }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                Bearbeiten
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                            Noch keine Artikel angelegt — werden beim Erstellen einer Rechnung automatisch übernommen.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </section>

</div>

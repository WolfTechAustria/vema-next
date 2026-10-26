<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Suchmaschinen</h2>
            <p class="mt-1 text-sm text-slate-500">Damit Google &amp; Co. die Homepage finden – und alte Adressen nicht ins Leere führen.</p>
        </div>

        <a href="{{ route('homepage.home') }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Homepage ansehen ↗
        </a>
    </div>

    @include('partials.flash-messages')

    <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold">Automatisch erledigt</h3>

        <ul class="list-disc space-y-1 pl-5 text-sm text-slate-600">
            <li>Verzeichnis aller Seiten und Beiträge für Suchmaschinen (Sitemap) und eine passende robots.txt.</li>
            <li>Jede Seite ist nur unter einer Adresse erreichbar: „www.“ wird auf die Domain ohne „www.“ umgeleitet.</li>
            <li>Verein, Beiträge und Termine werden Suchmaschinen zusätzlich als strukturierte Daten beschrieben.</li>
            <li>Titel und Beschreibung lassen sich je Beitrag und Seite unter „Suchmaschinen (Google)“ anpassen.</li>
            <li>Ändert sich die Adresse eines veröffentlichten Beitrags oder einer Seite, wird die alte Adresse weitergeleitet.</li>
        </ul>

        @if($publicRoot)
            <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                <div class="font-medium text-slate-900">Sitemap</div>
                <a href="{{ $sitemapUrl }}" target="_blank" class="break-all text-slate-600 underline hover:text-slate-900">{{ $sitemapUrl }}</a>
                <p class="mt-1 text-xs text-slate-500">
                    Tipp: In der <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="underline">Google Search Console</a>
                    die Domain bestätigen und diese Adresse unter „Sitemaps“ einreichen.
                </p>
            </div>
        @else
            <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Noch keine eigene Domain eingetragen (Homepage → Gestaltung). Suchmaschinen finden die Homepage erst unter der eigenen Domain.
            </p>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-5">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-3">
            <div>
                <h3 class="text-lg font-semibold">Weiterleitungen</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Für Adressen der früheren Homepage. Viele alte Adressen erkennt die Homepage selbst,
                    z. B. „/archive/titel-des-beitrags/“ → gleichnamiger Beitrag oder „/kategorie/…“ → News.
                    Endet die alte Adresse auf <code>*</code>, gilt die Weiterleitung für alle Adressen, die so beginnen.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
                <div>
                    <label class="mb-1 block text-sm font-medium">Alte Adresse</label>
                    <input type="text" wire:model="sourcePath" placeholder="/ergebnisse" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('sourcePath')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Ziel</label>
                    <input type="text" wire:model="target" placeholder="/news" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('target')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 sm:pt-6">
                    <button type="button" wire:click="saveRedirect" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        {{ $editingRedirectID ? 'Speichern' : 'Hinzufügen' }}
                    </button>

                    @if($editingRedirectID)
                        <button type="button" wire:click="cancelEdit" class="text-sm font-medium text-slate-500 hover:text-slate-800">Abbrechen</button>
                    @endif
                </div>
            </div>

            <div class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                @forelse($redirects as $redirect)
                    <div wire:key="redirect-{{ $redirect->redirectID }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div class="min-w-0 break-all">
                            <span class="font-medium text-slate-900">{{ $redirect->source_path }}</span>
                            <span class="text-slate-400">→</span>
                            <span class="text-slate-600">{{ $redirect->target }}</span>
                            <div class="text-xs text-slate-400">
                                {{ $redirect->hits }} × genutzt{{ $redirect->last_hit_at ? ', zuletzt am '.$redirect->last_hit_at->format('d.m.Y') : '' }}
                            </div>
                        </div>

                        <div class="flex flex-none gap-3">
                            <button type="button" wire:click="editRedirect({{ $redirect->redirectID }})" class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</button>
                            <button type="button" wire:click="deleteRedirect({{ $redirect->redirectID }})" wire:confirm="Weiterleitung wirklich löschen?" class="font-medium text-red-600 hover:text-red-800">Löschen</button>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-500">Noch keine eigenen Weiterleitungen.</div>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-6 py-4">
                <div>
                    <h3 class="font-semibold">Nicht gefunden</h3>
                    <p class="mt-1 text-xs text-slate-500">Adressen, die Besucher oder Suchmaschinen aufgerufen haben, die es aber nicht gibt.</p>
                </div>

                @if($notFound->isNotEmpty())
                    <button type="button" wire:click="clearNotFound" wire:confirm="Liste wirklich leeren?" class="flex-none text-xs font-medium text-slate-500 hover:text-slate-800">Leeren</button>
                @endif
            </div>

            <div class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
                @forelse($notFound as $entry)
                    <div wire:key="not-found-{{ $entry->notFoundID }}" class="flex items-start justify-between gap-3 px-6 py-3 text-sm">
                        <div class="min-w-0">
                            <div class="break-all font-medium text-slate-900">{{ $entry->path }}</div>
                            <div class="text-xs text-slate-400">{{ $entry->hits }} × aufgerufen, zuletzt {{ $entry->last_seen_at?->format('d.m.Y H:i') }}</div>
                        </div>

                        <div class="flex flex-none gap-3 text-xs">
                            <button type="button" wire:click="redirectNotFound({{ $entry->notFoundID }})" class="font-medium text-slate-600 hover:text-slate-900">Weiterleiten</button>
                            <button type="button" wire:click="dismissNotFound({{ $entry->notFoundID }})" class="font-medium text-slate-400 hover:text-slate-700">Ausblenden</button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-500">Bisher wurden keine fehlenden Adressen aufgerufen.</div>
                @endforelse
            </div>
        </section>

    </div>

</div>

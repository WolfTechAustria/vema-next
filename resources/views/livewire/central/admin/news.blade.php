<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Neuigkeiten</h1>
            <p class="mt-1 text-sm text-slate-500">
                Neue Funktionen und Hinweise für die Admins der Vereine. Sie erscheinen in VEMA unter „Neuigkeiten“ mit Hinweis im Dashboard,
                auf Wunsch zusätzlich per E-Mail an die Kontaktadresse jedes Vereins.
            </p>
        </div>

        @unless($showForm)
            <button type="button" wire:click="newNews" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                + Neuigkeit
            </button>
        @endunless
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($showForm)
        <section class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-semibold">{{ $editingNewsId ? 'Neuigkeit bearbeiten' : 'Neue Neuigkeit' }}</h2>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Titel</label>
                <input type="text" wire:model="title" placeholder="z. B. Eigene Mitgliederfelder und Ehrungen" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Text</label>
                <textarea wire:model="body" rows="10" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Markdown: **fett**, *kursiv*, Listen mit „- “, Links als [Text](https://…).</p>
                @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap items-end gap-6">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Für Vereine</label>
                    <select wire:model="minPlan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">alle Pakete</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->value }}">ab {{ $plan->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @if($editingNews?->emailed_at)
                    <p class="text-sm text-slate-500">Am {{ $editingNews->emailed_at->format('d.m.Y') }} an {{ $editingNews->emailed_count }} Vereine gemailt.</p>
                @else
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="sendEmail" class="rounded border-slate-300">
                        Beim Veröffentlichen per E-Mail an alle Vereine senden
                    </label>
                @endif
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <button type="button" wire:click="cancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</button>
                <button type="button" wire:click="saveDraft" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ $editingNews?->isPublished() ? 'Speichern' : 'Als Entwurf speichern' }}
                </button>
                <button
                    type="button"
                    wire:click="publish"
                    wire:confirm="{{ $editingNews?->isPublished() ? 'Änderungen speichern?' : 'Jetzt für alle Vereine veröffentlichen?' }}"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                >
                    {{ $editingNews?->isPublished() ? 'Speichern und ggf. mailen' : 'Veröffentlichen' }}
                </button>
            </div>
        </section>
    @endif

    <div class="space-y-3">
        @forelse($newsItems as $news)
            <article wire:key="news-{{ $news->id }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $news->title }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if($news->isPublished())
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">veröffentlicht {{ $news->published_at->format('d.m.Y H:i') }}</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-800">Entwurf</span>
                            @endif
                            · {{ $news->min_plan ? 'ab '.$news->min_plan->label() : 'alle Pakete' }}
                            @if($news->emailed_at) · gemailt an {{ $news->emailed_count }} Vereine @endif
                            @if($news->admin) · {{ $news->admin->name }} @endif
                        </p>
                    </div>

                    <div class="flex gap-3 text-sm">
                        <button type="button" wire:click="editNews({{ $news->id }})" class="text-slate-600 hover:underline">Bearbeiten</button>
                        @if($news->isPublished())
                            <button type="button" wire:click="unpublish({{ $news->id }})" wire:confirm="Wieder zurückziehen (Entwurf)?" class="text-slate-600 hover:underline">Zurückziehen</button>
                        @endif
                        <button type="button" wire:click="deleteNews({{ $news->id }})" wire:confirm="„{{ $news->title }}“ löschen?" class="text-red-700 hover:underline">Löschen</button>
                    </div>
                </div>

                <div class="mt-3 space-y-2 text-sm text-slate-700 [&_a]:underline [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">{{ $news->bodyHtml() }}</div>
            </article>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">Noch keine Neuigkeiten.</p>
        @endforelse
    </div>

</div>

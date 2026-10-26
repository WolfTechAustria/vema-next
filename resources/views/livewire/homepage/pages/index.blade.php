<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">Seiten</h2>

        <p class="mt-1 text-sm text-slate-500">
            Zusätzliche Seiten der Homepage, z. B. Impressum, Datenschutz, Vereinsgeschichte oder „Mitglied werden“.
            Startseite, News, Über uns, Vorstand und Kontakt gibt es automatisch.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 xl:grid-cols-5">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-3">

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">
                    {{ $editingPageID ? 'Seite bearbeiten' : 'Neue Seite' }}
                </h3>

                @if($editingPageID)
                    <button type="button" wire:click="cancelEdit" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                        Abbrechen
                    </button>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Titel</label>
                    <input type="text" wire:model.live.debounce.500ms="title" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Adresse</label>
                    <div class="flex items-center rounded-lg border border-slate-300">
                        <span class="pl-3 text-sm text-slate-400">/</span>
                        <input type="text" wire:model.blur="slug" class="w-full rounded-lg border-0 px-1 py-2 focus:ring-0">
                    </div>
                    @error('slug')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Überschrift im Seitenkopf
                    <span class="font-normal text-slate-400">(optional, z. B. „Jeden Montag ab 18 Uhr“ – der Titel steht dann klein darüber)</span>
                </label>
                <input type="text" wire:model="subtitle" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('subtitle')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Inhalt</label>

                <div
                    wire:key="page-editor-{{ $editingPageID ?? 'create' }}-{{ $formVersion }}"
                    wire:ignore
                    x-data="{ quill: null }"
                    x-init="
                        quill = new Quill($refs.editor, {
                            theme: 'snow',
                            modules: {
                                toolbar: [
                                    [{ header: [2, 3, false] }],
                                    ['bold', 'italic', 'underline'],
                                    ['link', 'blockquote'],
                                    [{ list: 'ordered' }, { list: 'bullet' }],
                                    ['clean']
                                ]
                            }
                        });

                        quill.root.innerHTML = @js($bodyHtml) || '';

                        quill.on('text-change', () => {
                            $wire.set('bodyHtml', quill.root.innerHTML, false);
                        });
                    "
                >
                    <div x-ref="editor" class="min-h-[260px] bg-white"></div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">Reihenfolge</label>
                    <input type="number" min="0" max="999" wire:model="sort" class="w-20 rounded-lg border border-slate-300 px-2 py-1">
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="inMenu" class="rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Im Menü anzeigen</span>
                </label>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="published" class="rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Veröffentlicht</span>
                </label>
            </div>

            <x-seo-fields :open="$seoTitle !== '' || $seoDescription !== ''" :title-placeholder="$title" description-placeholder="Leer = allgemeine Beschreibung der Homepage" />

            <p class="text-xs text-slate-500">
                Nicht im Menü angezeigte Seiten (z. B. Impressum) erscheinen als Link in der Fußzeile.
            </p>

            <button
                type="button"
                wire:click="savePage"
                wire:loading.attr="disabled"
                wire:target="savePage"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
            >
                {{ $editingPageID ? 'Änderungen speichern' : 'Seite anlegen' }}
            </button>

        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold">Bestehende Seiten</h3>
            </div>

            <div class="flex items-start justify-between gap-4 border-b border-slate-100 bg-slate-50 px-6 py-4">
                <div>
                    <div class="font-medium text-slate-900">Über uns <span class="text-xs font-normal text-slate-500">(feste Seite)</span></div>
                    <div class="mt-1 text-xs text-slate-500">Text unter Homepage → Gestaltung, dazu beliebige Abschnitte.</div>
                </div>
                <a href="{{ route('homepage-admin.about') }}" wire:navigate class="flex-none text-sm font-medium text-slate-600 hover:text-slate-900">Abschnitte ({{ $aboutSectionsCount }})</a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($pages as $page)
                    <div wire:key="page-{{ $page->pageID }}" class="flex items-start justify-between gap-4 px-6 py-4">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900">{{ $page->title }}</div>

                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                <span>/{{ $page->slug }}</span>

                                @if($page->published)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 font-medium text-green-700">veröffentlicht</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Entwurf</span>
                                @endif

                                <span>{{ $page->in_menu ? 'Menü' : 'Fußzeile' }}</span>
                            </div>
                        </div>

                        <div class="flex flex-none items-center gap-3 text-sm">
                            <a href="{{ route('homepage-admin.pages.sections', $page) }}" wire:navigate class="font-medium text-slate-600 hover:text-slate-900">Abschnitte ({{ $page->sections_count }})</a>
                            <a href="{{ route('homepage.page', $page->slug) }}" target="_blank" class="font-medium text-slate-600 hover:text-slate-900">Ansehen</a>
                            <button type="button" wire:click="editPage({{ $page->pageID }})" class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</button>
                            <button
                                type="button"
                                wire:click="deletePage({{ $page->pageID }})"
                                wire:confirm="Seite wirklich löschen?"
                                class="font-medium text-red-600 hover:text-red-800"
                            >Löschen</button>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-slate-500">
                        Noch keine eigenen Seiten vorhanden.
                    </div>
                @endforelse
            </div>

        </section>

    </div>

</div>

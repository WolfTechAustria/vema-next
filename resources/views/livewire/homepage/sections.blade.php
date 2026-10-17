@php
    use App\Enums\HomepageSectionType;

    $editingType = $editingSection?->type;
    $hasText = in_array($editingType, [HomepageSectionType::Text, HomepageSectionType::TextImage, HomepageSectionType::TextBox, HomepageSectionType::CallToAction], true);
    $hasHeading = $editingType !== HomepageSectionType::ImagePair;
    $hasButton = in_array($editingType, [HomepageSectionType::TextBox, HomepageSectionType::CallToAction], true);
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('homepage-admin.pages.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">← Alle Seiten</a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">
                Abschnitte: {{ $page?->title ?? 'Über uns' }}
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Bauen Sie die Seite aus Abschnitten zusammen. Sie erscheinen in dieser Reihenfolge
                @if($page)
                    unter dem Inhalt der Seite.
                @else
                    unter dem Text „Über uns“ (Homepage → Gestaltung).
                @endif
            </p>
        </div>

        <a
            href="{{ $page ? route('homepage.page', $page->slug) : route('homepage.about') }}"
            target="_blank"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Seite ansehen ↗
        </a>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 xl:grid-cols-5">

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold">Abschnitte</h3>
            </div>

            <ol class="divide-y divide-slate-100">
                @forelse($sections as $section)
                    <li
                        wire:key="section-{{ $section->sectionID }}"
                        class="flex items-start justify-between gap-3 px-6 py-3 {{ $editingSectionID === $section->sectionID ? 'bg-slate-50' : '' }}"
                    >
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ $loop->iteration }}. {{ $section->type->label() }}
                                @if($section->alt_background) · grauer Hintergrund @endif
                            </div>
                            <div class="truncate font-medium text-slate-900">
                                {{ $section->heading ?: (\Illuminate\Support\Str::limit(strip_tags((string) $section->body_html), 50) ?: '–') }}
                            </div>
                        </div>

                        <div class="flex flex-none items-center gap-2 text-sm">
                            @unless($loop->first)
                                <button type="button" wire:click="moveSection({{ $section->sectionID }}, 'up')" class="text-slate-500 hover:text-slate-800" title="Nach oben">↑</button>
                            @endunless
                            @unless($loop->last)
                                <button type="button" wire:click="moveSection({{ $section->sectionID }}, 'down')" class="text-slate-500 hover:text-slate-800" title="Nach unten">↓</button>
                            @endunless
                            <button type="button" wire:click="editSection({{ $section->sectionID }})" class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</button>
                            <button
                                type="button"
                                wire:click="deleteSection({{ $section->sectionID }})"
                                wire:confirm="Abschnitt wirklich löschen?"
                                class="font-medium text-red-600 hover:text-red-800"
                            >Löschen</button>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-slate-500">Noch keine Abschnitte.</li>
                @endforelse
            </ol>

            <div class="border-t border-slate-200 p-6">
                <h4 class="mb-3 text-sm font-semibold text-slate-700">Abschnitt hinzufügen</h4>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($sectionTypes as $sectionType)
                        <button
                            type="button"
                            wire:click="addSection('{{ $sectionType->value }}')"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-left hover:border-slate-400 hover:bg-slate-50"
                        >
                            <span class="block text-sm font-semibold text-slate-900">+ {{ $sectionType->label() }}</span>
                            <span class="block text-xs text-slate-500">{{ $sectionType->description() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-3">
            @if(! $editingSection)
                <div class="py-16 text-center text-sm text-slate-500">
                    Abschnitt links zum Bearbeiten auswählen oder einen neuen hinzufügen.
                </div>
            @else
                <div class="space-y-4" wire:key="section-form-{{ $editingSectionID }}-{{ $formVersion }}">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ $editingType->label() }}</h3>
                        <button type="button" wire:click="cancelEdit" class="text-sm font-medium text-slate-500 hover:text-slate-800">Schließen</button>
                    </div>

                    @if($hasHeading)
                        <div>
                            <label class="mb-1 block text-sm font-medium">Überschrift</label>
                            <input type="text" wire:model="heading" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('heading') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if($hasText)
                        <div>
                            <label class="mb-1 block text-sm font-medium">Text</label>

                            <div
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
                                <div x-ref="editor" class="min-h-[200px] bg-white"></div>
                            </div>

                            @if($editingType === HomepageSectionType::TextBox)
                                <p class="mt-1 text-xs text-slate-500">Nummerierte Listen erscheinen in der Vorlage „Verein“ als Schritte mit Kreis-Nummern.</p>
                            @endif
                        </div>
                    @endif

                    @if($editingType === HomepageSectionType::TextImage)
                        @include('livewire.homepage.partials.section-image', ['field' => 'image', 'label' => 'Bild', 'url' => $imageUrls->sectionMedia($editingSection, 'bild')])

                        <div class="flex items-center gap-4 text-sm">
                            <span class="font-medium">Bild steht</span>
                            <label class="flex items-center gap-1"><input type="radio" wire:model="imagePosition" value="left"> links</label>
                            <label class="flex items-center gap-1"><input type="radio" wire:model="imagePosition" value="right"> rechts</label>
                        </div>
                    @endif

                    @if($editingType === HomepageSectionType::Features)
                        <div class="space-y-3">
                            @foreach($items as $index => $item)
                                <div wire:key="item-{{ $index }}" class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[110px_1fr_auto]">
                                    <input type="text" wire:model="items.{{ $index }}.value" placeholder="Zahl, z. B. 8" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                    <input type="text" wire:model="items.{{ $index }}.title" placeholder="Titel" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                    <button type="button" wire:click="removeItem({{ $index }})" class="text-sm text-red-600 hover:text-red-800">Entfernen</button>
                                    <textarea wire:model="items.{{ $index }}.text" rows="2" placeholder="Kurzer Text" class="rounded-md border border-slate-300 px-2 py-1 text-sm sm:col-span-3"></textarea>
                                </div>
                            @endforeach

                            @if(count($items) < 4)
                                <button type="button" wire:click="addItem" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Kachel</button>
                            @endif
                        </div>
                    @endif

                    @if($editingType === HomepageSectionType::ImagePair)
                        @foreach([0 => ['image', 'bild', 'Erstes Bild'], 1 => ['image2', 'bild2', 'Zweites Bild']] as $index => [$field, $kind, $label])
                            <div class="space-y-2 rounded-lg border border-slate-200 p-3">
                                @include('livewire.homepage.partials.section-image', ['field' => $field, 'label' => $label, 'url' => $imageUrls->sectionMedia($editingSection, $kind)])
                                <input type="text" wire:model="items.{{ $index }}.title" placeholder="Bildunterschrift fett, z. B. Gründungsausschuss 1991" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <textarea wire:model="items.{{ $index }}.text" rows="2" placeholder="Bildunterschrift, z. B. von links: …" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></textarea>
                            </div>
                        @endforeach
                    @endif

                    @if($editingType === HomepageSectionType::TextBox)
                        <fieldset class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <legend class="px-1 text-sm font-semibold">Info-Box</legend>
                            <input type="text" wire:model="boxTitle" placeholder="Titel, z. B. Mitgliedsbeitrag" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <input type="text" wire:model="boxValue" placeholder="Große Angabe, z. B. € 40,–" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                <input type="text" wire:model="boxNote" placeholder="Darunter, z. B. pro Saison" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                            </div>
                            <textarea wire:model="boxText" rows="2" placeholder="Kurzer Text" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></textarea>
                        </fieldset>
                    @endif

                    @if($hasButton)
                        <fieldset class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <legend class="px-1 text-sm font-semibold">Button</legend>
                            <input type="text" wire:model="buttonLabel" placeholder="Text, z. B. Beitrittserklärung (PDF)" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                            @error('buttonLabel') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <input type="text" wire:model="buttonUrl" placeholder="Link, z. B. https://… oder /kontakt" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                            @error('buttonUrl') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="buttonToMemberArea" class="rounded border-slate-300">
                                Button führt zum Mitgliederbereich von VEMA
                            </label>

                            <div class="text-sm">
                                <span class="font-medium">oder PDF hochladen:</span>
                                @if($editingSection->file_path)
                                    <a href="{{ $imageUrls->sectionMedia($editingSection, 'datei') }}" target="_blank" class="ml-2 text-slate-600 underline">PDF ansehen</a>
                                    <button type="button" wire:click="removeMedia('file')" class="ml-2 text-red-600">entfernen</button>
                                @endif
                                <input type="file" wire:model="file" accept="application/pdf" class="mt-1 block text-sm">
                                @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <p class="text-xs text-slate-500">Reihenfolge: hochgeladenes PDF, dann Mitgliederbereich, dann Link.</p>
                        </fieldset>
                    @endif

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="altBackground" class="rounded border-slate-300">
                        Grauer Hintergrund (zur Abgrenzung von Nachbar-Abschnitten)
                    </label>

                    <button
                        type="button"
                        wire:click="saveSection"
                        wire:loading.attr="disabled"
                        wire:target="saveSection,image,image2,file"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        Abschnitt speichern
                    </button>
                </div>
            @endif
        </section>

    </div>

</div>

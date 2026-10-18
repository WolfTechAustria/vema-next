@php
    use App\Enums\HomepageSectionType;

    $editingType = $editingSection?->type;
    $hasText = in_array($editingType, [HomepageSectionType::Text, HomepageSectionType::TextImage, HomepageSectionType::TextBox, HomepageSectionType::CallToAction, HomepageSectionType::ItemList], true);
    $hasHeading = ! in_array($editingType, [HomepageSectionType::ImagePair, HomepageSectionType::Columns, HomepageSectionType::InfoBox], true);
    $hasButton = in_array($editingType, [HomepageSectionType::TextBox, HomepageSectionType::CallToAction, HomepageSectionType::InfoBox], true);
    $hasBox = in_array($editingType, [HomepageSectionType::TextBox, HomepageSectionType::InfoBox], true);
    $inColumnForm = $editingSection?->parentID !== null;
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
                    <li wire:key="section-{{ $section->sectionID }}" class="px-6 py-3">
                        @include('livewire.homepage.partials.section-row', [
                            'label' => $loop->iteration.'. ',
                            'isFirst' => $loop->first,
                            'isLast' => $loop->last,
                            'inColumn' => false,
                        ])

                        @if($section->type->isContainer())
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                @foreach(['left' => 'Links', 'right' => 'Rechts'] as $columnKey => $columnLabel)
                                    @php($columnChildren = $section->children->where('column', $columnKey)->values())

                                    <div wire:key="section-{{ $section->sectionID }}-{{ $columnKey }}" class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-2">
                                        <div class="mb-1 px-2 text-xs font-bold uppercase tracking-wide text-slate-500">{{ $columnLabel }}</div>

                                        <div class="space-y-1">
                                            @forelse($columnChildren as $child)
                                                <div wire:key="section-{{ $child->sectionID }}">
                                                    @include('livewire.homepage.partials.section-row', [
                                                        'section' => $child,
                                                        'label' => '',
                                                        'isFirst' => $loop->first,
                                                        'isLast' => $loop->last,
                                                        'inColumn' => true,
                                                    ])
                                                </div>
                                            @empty
                                                <p class="px-2 py-1 text-xs text-slate-400">Noch leer.</p>
                                            @endforelse
                                        </div>

                                        <details class="mt-2 px-2">
                                            <summary class="cursor-pointer text-xs font-semibold text-slate-600 hover:text-slate-900">+ Abschnitt hinzufügen</summary>

                                            <div class="mt-2 grid gap-1">
                                                @foreach($sectionTypes as $childType)
                                                    @continue($childType->isContainer())
                                                    <button
                                                        type="button"
                                                        wire:click="addSection('{{ $childType->value }}', {{ $section->sectionID }}, '{{ $columnKey }}')"
                                                        class="rounded px-2 py-1 text-left text-xs text-slate-700 hover:bg-white"
                                                    >{{ $childType->label() }}</button>
                                                @endforeach
                                            </div>
                                        </details>
                                    </div>
                                @endforeach
                            </div>
                        @endif
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

                    @if($editingType === HomepageSectionType::Events)
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-medium" for="event-limit">Anzahl Termine</label>
                            <input id="event-limit" type="number" min="1" max="10" wire:model="eventLimit" class="w-20 rounded-lg border border-slate-300 px-2 py-1">
                            @error('eventLimit') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <p class="text-xs text-slate-500">Zeigt die nächsten Vereinstermine mit dem Häkchen „Auf der Homepage zeigen“ (Termine → Termin bearbeiten).</p>
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

                    @if($editingType === HomepageSectionType::Image)
                        @include('livewire.homepage.partials.section-image', ['field' => 'image', 'label' => 'Bild', 'url' => $imageUrls->sectionMedia($editingSection, 'bild')])

                        <div>
                            <label class="mb-1 block text-sm font-medium">Bildunterschrift <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="text" wire:model="imageCaption" maxlength="300" placeholder="z. B. Unser Schießstand nach der Modernisierung 2009" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        @unless($inColumnForm)
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="font-medium">Breite</span>
                                <select wire:model="imageWidth" class="rounded-lg border border-slate-300 px-3 py-1.5">
                                    @foreach($imageWidths as $widthValue => $widthLabel)
                                        <option value="{{ $widthValue }}">{{ $widthLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless
                    @endif

                    @if($editingType === HomepageSectionType::Columns)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Breite der Spalten</label>
                                <select wire:model="columnsRatio" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @foreach($ratios as $ratioValue => $ratioLabel)
                                        <option value="{{ $ratioValue }}">{{ $ratioLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Ausrichtung</label>
                                <select wire:model="columnsAlign" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    <option value="top">oben bündig</option>
                                    <option value="center">vertikal zentriert</option>
                                </select>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500">Die Inhalte der Spalten fügen Sie links in der Liste unter „Links“ bzw. „Rechts“ hinzu. Auf dem Handy stehen die Spalten untereinander.</p>
                    @endif

                    @if($editingType === HomepageSectionType::ItemList)
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <label class="text-sm font-medium">Darstellung</label>
                                <select wire:model="listStyle" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                                    @foreach($listStyles as $styleValue => $styleLabel)
                                        <option value="{{ $styleValue }}">{{ $styleLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @foreach($listItems as $index => $listItem)
                                <div wire:key="list-item-{{ $index }}" class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1fr_auto]">
                                    <input type="text" wire:model="listItems.{{ $index }}.title" placeholder="Stichwort (fett), z. B. Vorbeischauen" class="rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold">
                                    <button type="button" wire:click="removeListItem({{ $index }})" class="text-sm text-red-600 hover:text-red-800">Entfernen</button>
                                    <textarea wire:model="listItems.{{ $index }}.text" rows="2" placeholder="Text, z. B. Komm an einem Montag ab 18 Uhr zum Training." class="rounded-md border border-slate-300 px-2 py-1 text-sm sm:col-span-2"></textarea>
                                </div>
                            @endforeach

                            @if(count($listItems) < 12)
                                <button type="button" wire:click="addListItem" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Punkt</button>
                            @endif
                        </div>
                    @endif

                    @if($hasBox)
                        <fieldset class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <legend class="px-1 text-sm font-semibold">Info-Box</legend>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="boxFramed" class="rounded border-slate-300">
                                Rahmen anzeigen (abschalten, wenn z. B. nur ein Button gezeigt werden soll)
                            </label>
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
                                @php($pdfMaxKb = app(\App\Services\UploadLimits::class)->effectiveKb(\App\Services\HomepageFileService::MAX_SIZE_KB))
                                <span class="text-xs text-slate-400">(max. {{ \App\Services\UploadLimits::label($pdfMaxKb) }})</span>
                                <x-upload-guard :max-kb="$pdfMaxKb">
                                    <input type="file" wire:model="file" accept="application/pdf" class="mt-1 block text-sm">
                                </x-upload-guard>
                                <x-upload-error field="file" :max-kb="$pdfMaxKb" />
                            </div>

                            <p class="text-xs text-slate-500">Reihenfolge: hochgeladenes PDF, dann Mitgliederbereich, dann Link.</p>
                        </fieldset>
                    @endif

                    @if(! $inColumnForm)
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="font-medium">Abstand oben und unten</span>
                        <div class="inline-flex overflow-hidden rounded-lg border border-slate-300">
                            @foreach($spacings as $spacingValue => $spacingLabel)
                                <label class="cursor-pointer border-l border-slate-300 px-3 py-1.5 first:border-l-0 {{ $spacing === $spacingValue ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' }}">
                                    <input type="radio" wire:model.live="spacing" value="{{ $spacingValue }}" class="sr-only">
                                    {{ $spacingLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="altBackground" class="rounded border-slate-300">
                        Grauer Hintergrund (zur Abgrenzung von Nachbar-Abschnitten)
                    </label>
                    @endif

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

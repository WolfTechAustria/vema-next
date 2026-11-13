@props([
    'property',
    'value' => '',
    'toolbar' => 'full',
    'height' => 'min-h-[260px]',
])

{{--
    Hybrider Homepage-Editor: visueller Editor mit HTML-Blöcken und
    umschaltbarem Quelltext (resources/js/homepage-editor.js).
--}}
<div
    wire:ignore
    x-data="homepageEditor(@js($property), @js($value ?? ''), @js($toolbar))"
    {{ $attributes->merge(['class' => 'hp-editor']) }}
>
    <div class="mb-1 flex items-center justify-end gap-1 text-xs">
        <button
            type="button"
            @click="showEditor()"
            :class="mode === 'editor' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
            class="rounded-md border border-slate-300 px-2.5 py-1 font-medium"
        >
            Editor
        </button>
        <button
            type="button"
            @click="showSource()"
            :class="mode === 'source' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
            class="rounded-md border border-slate-300 px-2.5 py-1 font-medium"
        >
            HTML-Quelltext
        </button>
    </div>

    <div x-show="mode === 'editor'">
        <div x-ref="editor" class="{{ $height }} bg-white"></div>
    </div>

    <div x-show="mode === 'source'" x-cloak>
        <textarea
            x-model="source"
            @input.debounce.300ms="sourceChanged()"
            spellcheck="false"
            class="{{ $height }} block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs leading-relaxed"
        ></textarea>
        <p class="mt-1 text-xs text-slate-500">
            Erlaubt sind u. a. Tabellen, Bilder (https), Videos von YouTube/Vimeo, Google-Maps-Karten und Inline-Styles. Skripte, Formulare und andere Einbettungen werden beim Speichern entfernt.
        </p>
    </div>

    <div
        x-show="dialogOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        @keydown.escape.window="closeBlockDialog()"
    >
        <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" @click.outside="closeBlockDialog()">
            <h3 class="text-lg font-semibold" x-text="isEditingBlock ? 'HTML-Block bearbeiten' : 'HTML-Block einfügen'"></h3>
            <p class="mt-1 text-sm text-slate-500">
                Z. B. eine Tabelle oder den Einbettungscode eines YouTube-Videos einfügen. Der Block erscheint im Editor als Vorschau; Doppelklick bearbeitet ihn.
            </p>

            <textarea
                x-ref="blockInput"
                x-model="blockHtml"
                spellcheck="false"
                rows="12"
                placeholder="&lt;table&gt;…&lt;/table&gt;"
                class="mt-4 block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs"
            ></textarea>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <button
                        type="button"
                        x-show="isEditingBlock"
                        @click="removeBlock()"
                        class="text-sm text-red-600 hover:text-red-800"
                    >
                        Block entfernen
                    </button>
                </div>

                <div class="flex gap-2">
                    <button type="button" @click="closeBlockDialog()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">
                        Abbrechen
                    </button>
                    <button type="button" @click="saveBlock()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        <span x-text="isEditingBlock ? 'Übernehmen' : 'Einfügen'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

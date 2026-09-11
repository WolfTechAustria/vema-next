<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Templates
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Texte für Vorschreibungen,
            Rundschreiben und Dokumente verwalten.
        </p>
    </div>


    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif


    <div class="mb-6">
        <label class="mb-1 block text-sm font-medium text-slate-700">
            Template auswählen
        </label>

        <select
            wire:model.live="selectedTemplateID"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
        >
            @foreach($templates as $template)
                <option value="{{ $template->templateID }}">
                    {{ $template->name }}
                </option>
            @endforeach
        </select>
    </div>


    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h3 class="font-semibold">
                Template bearbeiten
            </h3>

        </div>


        <div class="space-y-6 p-6">

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Bezeichnung
                </label>

                <input
                    type="text"
                    wire:model="name"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >
            </div>


            <div>
                <label class="mb-1 block text-sm font-medium">
                    Betreff
                </label>

                <input
                    type="text"
                    wire:model="subject"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >
            </div>


            <div>
                <label class="mb-1 block text-sm font-medium">
                    Text
                </label>

                <div
                    wire:key="template-editor-{{ $selectedTemplateID }}"
                    x-data="templateEditor(@entangle('bodyHtml'))"
                    wire:ignore
                    class="space-y-3"
                >
                    <div
                        x-ref="editor"
                        class="min-h-[320px] bg-white"
                    ></div>
                </div>
            </div>


            <div class="rounded-lg bg-slate-50 p-4">

                <div class="mb-2 text-sm font-semibold">
                    Verfügbare Platzhalter
                </div>

                <div class="flex flex-wrap gap-2 text-xs">

                    @foreach([
                        '@{{first_name}}',
                        '@{{last_name}}',
                        '@{{full_name}}',
                        '@{{street}}',
                        '@{{zip}}',
                        '@{{city}}',
                        '@{{year}}',
                        '@{{amount}}',
                        '@{{due_date}}',
                        '@{{salutation}}',
                        '@{{reminder_level}}'
                    ] as $placeholder)

                        <code class="rounded border border-slate-200 bg-white px-2 py-1">
                            {{ $placeholder }}
                        </code>

                    @endforeach

                </div>

            </div>

        </div>


        <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">

            <button
                type="button"
                wire:click="save"
                class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white"
            >
                Template speichern
            </button>

            <a
                href="{{ route('templates.preview', $selectedTemplateID) }}"
                target="_blank"
                class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                PDF Vorschau
            </a>

        </div>

    </section>




</div>

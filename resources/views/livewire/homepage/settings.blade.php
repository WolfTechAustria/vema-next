<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Gestaltung</h2>

            <p class="mt-1 text-sm text-slate-500">
                Vorlage, Farben und Inhalte der öffentlichen Vereinshomepage.
            </p>
        </div>

        <a
            href="{{ route('homepage.home') }}"
            target="_blank"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Homepage ansehen ↗
        </a>
    </div>

    @include('partials.flash-messages')

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="enabled" class="mt-1 rounded border-slate-300">

            <span>
                <span class="block font-semibold text-slate-900">Homepage ist öffentlich erreichbar</span>
                <span class="block text-sm text-slate-500">
                    Solange ausgeschaltet, sehen nur angemeldete Homepage-Verantwortliche die Seiten (Vorschau).
                </span>
            </span>
        </label>
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold">Vorlage</h3>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($templates as $templateOption)
                <label
                    wire:key="template-{{ $templateOption->value }}"
                    class="cursor-pointer rounded-xl border-2 p-3 transition
                    {{ $template === $templateOption->value ? 'border-slate-900' : 'border-slate-200 hover:border-slate-300' }}"
                >
                    <input type="radio" wire:model.live="template" value="{{ $templateOption->value }}" class="sr-only">

                    @include('homepage.partials.template-thumbnail', ['templateKey' => $templateOption->value, 'color' => $primaryColor, 'accent' => $accentColor])

                    <span class="mt-3 block font-semibold text-slate-900">{{ $templateOption->label() }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ $templateOption->description() }}</span>
                </label>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-sm font-medium" for="primary-color">Hauptfarbe</label>
            <input id="primary-color" type="color" wire:model.live="primaryColor" class="h-9 w-14 cursor-pointer rounded border border-slate-300">
            <span class="font-mono text-sm text-slate-500">{{ $primaryColor }}</span>

            @error('primaryColor')
            <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror

            <label class="ml-4 text-sm font-medium" for="accent-color">Akzentfarbe</label>
            <input id="accent-color" type="color" wire:model.live="accentColor" class="h-9 w-14 cursor-pointer rounded border border-slate-300">
            <span class="font-mono text-sm text-slate-500">{{ $accentColor }}</span>

            @error('accentColor')
            <p class="text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <p class="text-xs text-slate-500">Die Akzentfarbe hebt Datum, aktiven Menüpunkt und Hinweise hervor (Vorlage „Verein“).</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Startseite</h3>

            <div>
                <label class="mb-1 block text-sm font-medium">Überschrift</label>
                <input type="text" wire:model="heroTitle" placeholder="z. B. Willkommen beim Musikverein" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('heroTitle')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Begrüßungstext</label>
                <textarea wire:model="heroText" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                @error('heroText')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Titelbild</label>

                @if($heroUrl)
                    <img src="{{ $heroUrl }}" alt="" class="mb-2 aspect-[16/7] w-full rounded-lg object-cover">
                @endif

                <div class="flex flex-wrap items-center gap-3">
                    <label class="cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        {{ $heroUrl ? 'Bild ersetzen' : 'Bild hochladen' }}
                        <input type="file" wire:model="heroImage" accept="image/jpeg,image/png,image/webp" class="hidden">
                    </label>

                    @if($heroUrl)
                        <button type="button" wire:click="removeHeroImage" wire:confirm="Titelbild entfernen?" class="text-sm font-medium text-red-600 hover:text-red-800">
                            Entfernen
                        </button>
                    @endif

                    <span wire:loading wire:target="heroImage" class="text-sm text-slate-500">Wird hochgeladen …</span>
                </div>

                @error('heroImage')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Seiten &amp; Daten</h3>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="contactVisible" class="mt-1 rounded border-slate-300">
                <span class="text-sm">
                    <span class="block font-medium text-slate-900">Kontaktseite anzeigen</span>
                    <span class="block text-slate-500">Anschrift, E-Mail und Telefon aus den Vereinseinstellungen.</span>
                </span>
            </label>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model.live="showBoard" class="mt-1 rounded border-slate-300">
                <span class="text-sm">
                    <span class="block font-medium text-slate-900">Vorstand anzeigen</span>
                    <span class="block text-slate-500">Name und Funktion aller aktuell eingetragenen Vorstandsfunktionen.</span>
                </span>
            </label>

            @if($showBoard)
                <label class="ml-7 flex items-start gap-3">
                    <input type="checkbox" wire:model="showBoardPhotos" class="mt-1 rounded border-slate-300">
                    <span class="text-sm">
                        <span class="block font-medium text-slate-900">Mit Profilbildern</span>
                        <span class="block text-slate-500">Nur einschalten, wenn die Vorstandsmitglieder der Veröffentlichung ihres Fotos zugestimmt haben.</span>
                    </span>
                </label>
            @endif

            <div>
                <label class="mb-1 block text-sm font-medium">Beschreibung für Suchmaschinen</label>
                <textarea wire:model="metaDescription" rows="2" maxlength="300" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                @error('metaDescription')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </section>

    </div>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h3 class="text-lg font-semibold">Über uns</h3>
            <p class="mt-1 text-sm text-slate-500">Leer lassen, um die Seite „Über uns“ auszublenden.</p>
        </div>

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

                quill.root.innerHTML = @js($aboutHtml) || '';

                quill.on('text-change', () => {
                    $wire.set('aboutHtml', quill.root.innerHTML, false);
                });
            "
        >
            <div x-ref="editor" class="min-h-[220px] bg-white"></div>
        </div>
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold">Eigene Domain</h3>

        @if($isMultiTenant)
            @if($platformDomains !== [])
                <p class="text-sm text-slate-600">Die Homepage ist unter folgender Domain erreichbar:</p>

                <ul class="list-inside list-disc text-sm font-medium text-slate-900">
                    @foreach($platformDomains as $platformDomain)
                        <li><a href="https://{{ $platformDomain }}" target="_blank" class="hover:underline">{{ $platformDomain }}</a></li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-600">
                    Für die Homepage ist noch keine eigene Domain hinterlegt. Bitte melden Sie sich beim vemat.at-Support mit Ihrer
                    Wunschdomain (z. B. www.mein-verein.at). Bis dahin ist die Homepage unter
                    <a href="{{ route('homepage.home') }}" target="_blank" class="font-medium underline">{{ route('homepage.home') }}</a> erreichbar.
                </p>
            @endif
        @else
            <div>
                <label class="mb-1 block text-sm font-medium">Domain der Homepage</label>
                <input type="text" wire:model="domain" placeholder="mein-verein.at" class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2">
                @error('domain')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-slate-500">
                    Die Domain und die Variante mit „www.“ müssen per DNS (A-Eintrag) auf diesen Server zeigen und im Webserver
                    samt SSL-Zertifikat eingerichtet sein. Bis dahin ist die Homepage unter
                    <a href="{{ route('homepage.home') }}" target="_blank" class="underline">{{ route('homepage.home') }}</a> erreichbar.
                </p>
            </div>
        @endif
    </section>

    <div class="flex justify-end">
        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="rounded-lg bg-slate-900 px-6 py-2 text-sm font-semibold text-white disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="save">Einstellungen speichern</span>
            <span wire:loading wire:target="save">Speichern …</span>
        </button>
    </div>

</div>

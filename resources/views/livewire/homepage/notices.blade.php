<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Hinweise</h2>
            <p class="mt-1 text-sm text-slate-500">Cookie-Hinweis und Popup für wichtige Informationen auf der Startseite.</p>
        </div>

        <a href="{{ route('homepage.home') }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Homepage ansehen ↗
        </a>
    </div>

    @include('partials.flash-messages')

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-semibold">Popup auf der Startseite</h3>

            @if($popupIsActive)
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">wird gerade angezeigt</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">nicht sichtbar</span>
            @endif
        </div>

        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="popupEnabled" class="mt-1 rounded border-slate-300">
            <span class="text-sm">
                <span class="block font-medium text-slate-900">Popup anzeigen</span>
                <span class="block text-slate-500">Für wichtige Infos, z. B. Terminabsage, geänderte Trainingszeiten oder eine Einladung.</span>
            </span>
        </label>

        <div>
            <label class="mb-1 block text-sm font-medium">Titel</label>
            <input type="text" wire:model="popupTitle" placeholder="z. B. Training fällt diese Woche aus" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('popupTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Text</label>
            <div
                wire:ignore
                x-data="{ quill: null }"
                x-init="
                    quill = new Quill($refs.editor, {
                        theme: 'snow',
                        modules: { toolbar: [['bold', 'italic', 'underline'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] }
                    });
                    quill.root.innerHTML = @js($popupHtml) || '';
                    quill.on('text-change', () => { $wire.set('popupHtml', quill.root.innerHTML, false); });
                "
            >
                <div x-ref="editor" class="min-h-[160px] bg-white"></div>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Bild <span class="font-normal text-slate-400">(optional)</span></label>
            <div class="flex flex-wrap items-center gap-3">
                @if($popupImageUrl)
                    <img src="{{ $popupImageUrl }}" alt="" class="h-20 w-32 rounded-lg object-cover">
                    <button type="button" wire:click="removePopupImage" class="text-sm text-red-600 hover:text-red-800">Entfernen</button>
                @endif
                <input type="file" wire:model="popupImage" accept="image/jpeg,image/png,image/webp" class="text-sm">
                <span wire:loading wire:target="popupImage" class="text-xs text-slate-500">Wird hochgeladen …</span>
            </div>
            @error('popupImage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Button: Text <span class="font-normal text-slate-400">(optional)</span></label>
                <input type="text" wire:model="popupButtonLabel" placeholder="z. B. Mehr erfahren" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('popupButtonLabel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Button: Link</label>
                <input type="text" wire:model="popupButtonUrl" placeholder="z. B. /termine oder https://…" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('popupButtonUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Anzeigen ab</label>
                <input type="datetime-local" wire:model="popupStartsAt" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('popupStartsAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Anzeigen bis</label>
                <input type="datetime-local" wire:model="popupEndsAt" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('popupEndsAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Wie oft</label>
                <select wire:model="popupFrequency" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @foreach($frequencies as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="text-xs text-slate-500">Leer = sofort bzw. unbegrenzt. Nach einer Änderung von Titel, Text, Bild oder Button sehen alle Besucher das Popup erneut.</p>
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold">Cookie-Hinweis</h3>

        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="cookieBannerEnabled" class="mt-1 rounded border-slate-300">
            <span class="text-sm">
                <span class="block font-medium text-slate-900">Cookie-Hinweis anzeigen</span>
                <span class="block text-slate-500">Leiste am unteren Rand, bis der Besucher „Verstanden“ klickt.</span>
            </span>
        </label>

        <div>
            <label class="mb-1 block text-sm font-medium">Text</label>
            <textarea wire:model="cookieBannerText" rows="3" maxlength="600" placeholder="{{ $defaultCookieText }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            @error('cookieBannerText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">Leer = Standardtext.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Link „Datenschutz“ auf Seite</label>
            <select wire:model="cookieBannerPageID" class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2">
                <option value="">– kein Link –</option>
                @foreach($pages as $page)
                    <option value="{{ $page->pageID }}">{{ $page->title }}{{ $page->published ? '' : ' (Entwurf)' }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">Datenschutzerklärung zuerst unter <a href="{{ route('homepage-admin.pages.index') }}" wire:navigate class="underline">Seiten</a> anlegen.</p>
        </div>

        <p class="rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
            Die Homepage setzt nur technisch notwendige Cookies (Sitzung und Schutz von Formularen) und lädt keine Inhalte von fremden Servern – daher genügt ein Hinweis ohne Auswahlmöglichkeit.
            Werden später Dienste wie Statistik oder eingebettete Videos ergänzt, braucht es eine echte Einwilligung.
        </p>
    </section>

    <div class="flex justify-end">
        <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-slate-900 px-6 py-2 text-sm font-semibold text-white disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Hinweise speichern</span>
            <span wire:loading wire:target="save">Speichern …</span>
        </button>
    </div>

</div>

<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Inhalte</h2>

            <p class="mt-1 text-sm text-slate-500">
                Zusätzliche Elemente der Startseite, Überschriften der festen Seiten, Kontakttext und Fußzeile.
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

    @unless($usesVereinTemplate)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-900">
            Diese Inhalte zeigt die Vorlage <strong>Verein</strong>. Bei der aktuell gewählten Vorlage bleiben sie unsichtbar
            (Vorlage ändern unter <a href="{{ route('homepage-admin.settings') }}" wire:navigate class="font-semibold underline">Gestaltung</a>).
        </div>
    @endunless

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-semibold">Titelbereich der Startseite</h3>

        <div>
            <label class="mb-1 block text-sm font-medium">Zeile über der Überschrift</label>
            <input type="text" wire:model="heroEyebrow" placeholder="z. B. Sportschießen in Angerberg seit 1991" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('heroEyebrow') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Zweiter Button: Text</label>
                <input type="text" wire:model="heroButtonLabel" placeholder="z. B. Probetraining (leer = Kontakt)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('heroButtonLabel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Zweiter Button: Link</label>
                <input type="text" wire:model="heroButtonUrl" placeholder="z. B. /training" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('heroButtonUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <p class="text-xs text-slate-500">Überschrift, Begrüßungstext und Titelbild stellen Sie unter <a href="{{ route('homepage-admin.settings') }}" wire:navigate class="underline">Gestaltung</a> ein. Links auf eigene Seiten beginnen mit „/“, z. B. „/training“.</p>
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model.live="announcementEnabled" class="mt-1 rounded border-slate-300">
            <span>
                <span class="block text-lg font-semibold text-slate-900">Ankündigung auf der Startseite</span>
                <span class="block text-sm text-slate-500">Großes Banner mit Bild, z. B. eine Einladung zu einer Veranstaltung.</span>
            </span>
        </label>

        @if($announcementEnabled)
            <div class="grid gap-4 sm:grid-cols-[160px_1fr]">
                <div>
                    <label class="mb-1 block text-sm font-medium">Kennzeichnung</label>
                    <input type="text" wire:model="announcementBadge" placeholder="Einladung" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Titel</label>
                    <input type="text" wire:model="announcementTitle" placeholder="z. B. Dorfmeisterschaft der 3 Gemeinden" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('announcementTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Text</label>
                <textarea wire:model="announcementText" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Button: Text</label>
                    <input type="text" wire:model="announcementLinkLabel" placeholder="z. B. Einladung öffnen (PDF)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Button: Link</label>
                    <input type="text" wire:model="announcementLinkUrl" placeholder="https://… (nicht nötig bei PDF)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('announcementLinkUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Bild</label>
                    @if($announcementImageUrl)
                        <img src="{{ $announcementImageUrl }}" alt="" class="mb-2 aspect-[16/9] w-full max-w-xs rounded-lg object-cover">
                        <button type="button" wire:click="removeAnnouncementMedia('image')" class="mb-2 block text-sm text-red-600">Bild entfernen</button>
                    @endif
                    <input type="file" wire:model="announcementImage" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    <span wire:loading wire:target="announcementImage" class="text-xs text-slate-500">Wird hochgeladen …</span>
                    @error('announcementImage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">PDF (z. B. Einladung)</label>
                    @if($announcementFileUrl)
                        <a href="{{ $announcementFileUrl }}" target="_blank" class="mr-3 text-sm text-slate-600 underline">PDF ansehen</a>
                        <button type="button" wire:click="removeAnnouncementMedia('file')" class="text-sm text-red-600">entfernen</button>
                    @endif
                    <input type="file" wire:model="announcementFile" accept="application/pdf" class="mt-1 block text-sm">
                    <span wire:loading wire:target="announcementFile" class="text-xs text-slate-500">Wird hochgeladen …</span>
                    @error('announcementFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-slate-500">Bild und PDF werden sofort gespeichert. Ist ein PDF hochgeladen, öffnet der Button das PDF.</p>
        @endif
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h3 class="text-lg font-semibold">Kacheln unter dem Titelbereich</h3>
            <p class="mt-1 text-sm text-slate-500">Bis zu drei Kacheln, z. B. Training, Über uns, Mitglied werden. Ohne Titel wird eine Kachel nicht angezeigt; sind alle leer, erscheinen automatisch Kacheln zu den Seiten im Menü.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach($tiles as $index => $tile)
                <div wire:key="tile-{{ $index }}" class="space-y-2 rounded-lg border-t-4 border-slate-300 bg-slate-50 p-4">
                    <input type="text" wire:model="tiles.{{ $index }}.kicker" placeholder="Kleine Zeile, z. B. Training" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                    <input type="text" wire:model="tiles.{{ $index }}.title" placeholder="Titel, z. B. Jeden Montag ab 18 Uhr" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold">
                    <textarea wire:model="tiles.{{ $index }}.text" rows="3" placeholder="Text" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm"></textarea>
                    <input type="text" wire:model="tiles.{{ $index }}.link_label" placeholder="Link-Text, z. B. Mehr zum Training" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                    <input type="text" wire:model="tiles.{{ $index }}.url" placeholder="Link, z. B. /training" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                    @error('tiles.'.$index.'.url') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
    </section>

    <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h3 class="text-lg font-semibold">Kennzahlen</h3>
            <p class="mt-1 text-sm text-slate-500">Dunkle Leiste am Ende der Startseite mit bis zu vier Zahlen, z. B. „1991 – gegründet“.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($stats as $index => $stat)
                <div wire:key="stat-{{ $index }}" class="space-y-2 rounded-lg bg-slate-50 p-3">
                    <input type="text" wire:model="stats.{{ $index }}.value" placeholder="Zahl, z. B. 1991" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm font-semibold">
                    <input type="text" wire:model="stats.{{ $index }}.label" placeholder="Beschriftung, z. B. gegründet" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h3 class="text-lg font-semibold">Überschriften der Seitenköpfe</h3>
                <p class="mt-1 text-sm text-slate-500">Der Seitenname steht klein darüber. Leer = Seitenname als Überschrift.</p>
            </div>

            @foreach($headingPages as $pageKey => $pageLabel)
                <div wire:key="heading-{{ $pageKey }}">
                    <label class="mb-1 block text-sm font-medium">{{ $pageLabel }}</label>
                    <input type="text" wire:model="pageHeadings.{{ $pageKey }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="{{ ['news' => 'z. B. Neuigkeiten & Wettkampfberichte', 'events' => 'z. B. Veranstaltungen & Wettkämpfe', 'about' => 'z. B. Klein, aber fein – seit 1991', 'board' => 'z. B. Unser Vorstand', 'contact' => 'z. B. Schreib oder ruf uns an'][$pageKey] }}">
                </div>
            @endforeach

            <div>
                <label class="mb-1 block text-sm font-medium">Bezeichnung der News im Menü</label>
                <input type="text" wire:model="pageHeadings.news_menu" placeholder="News (z. B. Aktuelles)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <p class="text-xs text-slate-500">Für eigene Seiten tragen Sie die Überschrift direkt bei der Seite ein.</p>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Kontakt &amp; Fußzeile</h3>

            <div>
                <label class="mb-1 block text-sm font-medium">Text auf der Kontaktseite</label>
                <textarea wire:model="contactText" rows="3" placeholder="z. B. Fragen zu Mitgliedschaft oder Training? Wir melden uns so schnell wie möglich." class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </div>

            <div class="grid gap-4 sm:grid-cols-[160px_1fr]">
                <div>
                    <label class="mb-1 block text-sm font-medium">Info-Spalte: Titel</label>
                    <input type="text" wire:model="footerInfoTitle" placeholder="z. B. Training" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Info-Spalte: Text</label>
                    <textarea wire:model="footerInfoText" rows="2" placeholder="z. B. Jeden Montag ab 18:00 Uhr im Schützenheim" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                </div>
            </div>

            <p class="text-xs text-slate-500">Die Info-Spalte erscheint in der Fußzeile und als Karte auf der Kontaktseite.</p>

            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="showMemberAreaLink" class="mt-1 rounded border-slate-300">
                <span class="text-sm">
                    <span class="block font-medium text-slate-900">Link „Mitgliederbereich“ in der Fußzeile</span>
                    <span class="block text-slate-500">Führt zur Anmeldung im Mitgliederbereich von VEMA.</span>
                </span>
            </label>
        </section>
    </div>

    <div class="flex justify-end">
        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="rounded-lg bg-slate-900 px-6 py-2 text-sm font-semibold text-white disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="save">Inhalte speichern</span>
            <span wire:loading wire:target="save">Speichern …</span>
        </button>
    </div>

</div>

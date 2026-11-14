<div class="space-y-6">

    @include('partials.flash-messages')

    <section
        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        x-data="pushDevice(@js($publicKey), @js($endpoints))"
    >
        <h3 class="font-semibold text-slate-900">
            Push-Benachrichtigungen auf diesem Gerät
        </h3>
        <p class="mt-1 text-sm text-slate-500">
            Erhalte wichtige Hinweise direkt als Benachrichtigung auf dem Handy oder Computer – auch wenn VEMA nicht geöffnet ist.
        </p>

        @unless($configured)
            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Push-Benachrichtigungen sind auf diesem Server noch nicht eingerichtet.
            </p>
        @else
            <div class="mt-4 space-y-3 text-sm">
                <template x-if="state === 'ios-install'">
                    <div class="rounded-lg bg-sky-50 px-4 py-3 text-sky-900">
                        Auf dem iPhone/iPad funktionieren Benachrichtigungen nur, wenn VEMA zum Home-Bildschirm hinzugefügt ist:
                        in Safari auf <strong>Teilen</strong> tippen, dann <strong>Zum Home-Bildschirm</strong>. Danach VEMA über das neue Symbol öffnen und hier aktivieren.
                    </div>
                </template>

                <template x-if="state === 'unsupported'">
                    <div class="rounded-lg bg-slate-50 px-4 py-3 text-slate-600">
                        Dieser Browser unterstützt keine Push-Benachrichtigungen.
                    </div>
                </template>

                <template x-if="state === 'denied'">
                    <div class="rounded-lg bg-amber-50 px-4 py-3 text-amber-900">
                        Benachrichtigungen sind für diese Seite blockiert. Bitte in den Browser-Einstellungen erlauben und die Seite neu laden.
                    </div>
                </template>

                <template x-if="state === 'off'">
                    <button
                        type="button"
                        x-on:click="enable()"
                        x-bind:disabled="busy"
                        class="rounded-lg bg-slate-900 px-4 py-2.5 font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                    >
                        Auf diesem Gerät aktivieren
                    </button>
                </template>

                <template x-if="state === 'on'">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                            ● Aktiv auf diesem Gerät
                        </span>
                        <button
                            type="button"
                            wire:click="sendTest"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Testnachricht senden
                        </button>
                        <button
                            type="button"
                            x-on:click="disable()"
                            x-bind:disabled="busy"
                            class="text-red-600 hover:text-red-800 disabled:opacity-50"
                        >
                            Auf diesem Gerät deaktivieren
                        </button>
                    </div>
                </template>

                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                @if($deviceCount > 0)
                    <p class="text-xs text-slate-500">
                        Insgesamt {{ $deviceCount }} {{ $deviceCount === 1 ? 'Gerät' : 'Geräte' }} angemeldet.
                    </p>
                @endif
            </div>
        @endunless
    </section>

    @if($configured)
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">
                Worüber möchtest du benachrichtigt werden?
            </h3>
            <p class="mt-1 text-sm text-slate-500">
                Gilt für alle deine Geräte.
            </p>

            <div class="mt-3 divide-y divide-slate-100">
                @foreach($categories as $category)
                    @php($isOn = in_array($category->value, $enabled, true))
                    <div wire:key="push-category-{{ $category->value }}" class="flex items-center justify-between gap-4 py-3">
                        <div>
                            <div class="text-sm font-medium text-slate-800">{{ $category->label() }}</div>
                            <div class="text-sm text-slate-500">{{ $category->description() }}</div>
                        </div>
                        <button
                            type="button"
                            wire:click="toggleCategory('{{ $category->value }}')"
                            aria-label="{{ $category->label() }}"
                            aria-pressed="{{ $isOn ? 'true' : 'false' }}"
                            class="{{ $isOn ? 'bg-emerald-600' : 'bg-slate-300' }} relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                        >
                            <span class="{{ $isOn ? 'translate-x-6' : 'translate-x-1' }} inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                        </button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

</div>

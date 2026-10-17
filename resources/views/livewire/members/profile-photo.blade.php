<div x-data="photoCropper" class="{{ $context === 'portal' ? 'flex flex-col items-center gap-4 sm:flex-row' : 'relative shrink-0' }}">

    <input
        x-ref="fileInput"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="sr-only"
        @change="load($event)"
    >

    @if($context === 'portal')

        <x-member-avatar
            :url="$photoUrl"
            :initials="$member->initials()"
            :name="$member->full_name"
            size="h-24 w-24 text-3xl"
        />

        <div class="text-center sm:text-left">
            <div class="flex flex-wrap justify-center gap-2 sm:justify-start">
                <button
                    type="button"
                    @click="choose()"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                >
                    {{ $photoUrl ? 'Foto ändern' : 'Foto hochladen' }}
                </button>

                @if($photoUrl)
                    <button
                        type="button"
                        wire:click="removePhoto"
                        wire:confirm="Profilbild entfernen?"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Foto entfernen
                    </button>
                @endif
            </div>

            <p class="mt-2 text-xs text-slate-500">
                JPG, PNG oder WEBP. Den Ausschnitt legst du nach der Auswahl selbst fest.
            </p>
        </div>

    @else

        <button
            type="button"
            @click="choose()"
            title="{{ $photoUrl ? 'Profilbild ändern' : 'Profilbild hochladen' }}"
            aria-label="{{ $photoUrl ? 'Profilbild ändern' : 'Profilbild hochladen' }}"
            class="group relative block rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2"
        >
            <x-member-avatar
                :url="$photoUrl"
                :initials="$member->initials()"
                :name="$member->full_name"
                size="h-16 w-16 text-xl"
            />

            <span class="absolute -bottom-0.5 -right-0.5 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-slate-900 text-white shadow transition group-hover:scale-110">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.6l1.2-1.8A1.5 1.5 0 0 1 9.55 3.5h4.9a1.5 1.5 0 0 1 1.25.7L16.9 6h1.6A2.5 2.5 0 0 1 21 8.5v9A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-9Z" />
                    <circle cx="12" cy="13" r="3.5" />
                </svg>
            </span>
        </button>

        @if($photoUrl)
            <button
                type="button"
                wire:click="removePhoto"
                wire:confirm="Profilbild von {{ $member->full_name }} entfernen?"
                class="mt-1 block w-16 text-center text-[11px] font-medium text-slate-400 hover:text-red-600"
            >
                Entfernen
            </button>
        @endif

    @endif

    {{-- Rückmeldungen --}}
    <div class="{{ $context === 'portal' ? '' : 'absolute left-0 top-full z-10 mt-1 w-56' }}">
        <p x-show="error" x-cloak x-text="error" class="text-xs text-red-600"></p>

        @error('photo')
            <p class="text-xs text-red-600">{{ $message }}</p>
        @enderror

        @if($statusMessage)
            <p
                wire:key="photo-status-{{ md5($statusMessage.microtime()) }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 3000)"
                x-show="show"
                x-transition.opacity
                role="status"
                class="text-xs font-medium text-emerald-700"
            >
                {{ $statusMessage }}
            </p>
        @endif
    </div>

    {{-- Zuschnitt --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="if (open && ! busy) cancel()"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4"
        >
            <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-xl bg-white shadow-2xl" @click.stop>

                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="font-semibold text-slate-900">Ausschnitt wählen</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Bild verschieben und mit Mausrad bzw. zwei Fingern zoomen. Der Rahmen lässt sich an den Ecken anpassen.
                    </p>
                </div>

                <div class="bg-slate-100">
                    <div class="mx-auto h-[min(60vh,420px)] w-full">
                        <img x-ref="image" alt="Ausgewähltes Bild" class="block max-w-full">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                    <div class="flex gap-1">
                        <button type="button" @click="zoom(-0.1)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" aria-label="Verkleinern">−</button>
                        <button type="button" @click="zoom(0.1)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" aria-label="Vergrößern">+</button>
                        <button type="button" @click="rotate()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" aria-label="Drehen">↻</button>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="cancel()" :disabled="busy" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 disabled:opacity-60">
                            Abbrechen
                        </button>
                        <button type="button" @click="apply()" :disabled="busy" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                            <span x-show="! busy">Übernehmen</span>
                            <span x-show="busy" x-cloak>Wird gespeichert …</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </template>

</div>

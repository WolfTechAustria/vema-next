<div class="space-y-6">

    @include('partials.flash-messages')

    <!-- Kalender-Abo -->
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="font-semibold text-slate-900">
            Kalender-Abo
        </h3>

        <p class="mt-1 text-sm text-slate-500">
            Dieser Link kann in Google Kalender, Apple Kalender oder Outlook
            als Kalender-Abo hinzugefügt werden. Er enthält alle Vereinstermine,
            für die du nicht abgesagt hast.
        </p>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                type="text"
                readonly
                value="{{ $icalUrl }}"
                onclick="this.select()"
                class="flex-1 rounded-md border-slate-300 bg-slate-50 text-sm"
            >

            <a
                href="{{ str_replace(['https://', 'http://'], 'webcal://', $icalUrl) }}"
                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                Zum Kalender hinzufügen
            </a>

            <button
                type="button"
                wire:click="regenerateIcalLink"
                wire:confirm="Alten Link ungültig machen und einen neuen erzeugen?"
                class="whitespace-nowrap rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Link erneuern
            </button>
        </div>
    </section>

    <!-- Anstehende Termine -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Anstehende Termine
            </h3>
        </div>

        @forelse ($events as $event)
            @php($response = $event->responseFor($member))

            <div
                wire:key="my-event-{{ $event->eventID }}"
                class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 last:border-b-0 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0">
                    <div class="font-medium text-slate-900">
                        {{ $event->title }}
                    </div>

                    <div class="text-sm text-slate-500">
                        {{ $event->formattedPeriod() }}
                        @if($event->location)
                            · {{ $event->location }}
                        @endif
                    </div>

                    @if($event->description)
                        <div class="mt-1 whitespace-pre-line text-sm text-slate-500">{{ $event->description }}</div>
                    @endif

                    @if($response)
                        <div class="mt-2 text-xs font-medium {{ $response->status === \App\Enums\EventResponseStatus::Attending ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $response->status->label() }}
                        </div>
                    @endif
                </div>

                @if($event->rsvp_enabled)
                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            wire:click="respond({{ $event->eventID }}, 'attending')"
                            @disabled($response?->status === \App\Enums\EventResponseStatus::Attending)
                            class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            Zusagen
                        </button>

                        <button
                            type="button"
                            wire:click="respond({{ $event->eventID }}, 'declined')"
                            @disabled($response?->status === \App\Enums\EventResponseStatus::Declined)
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            Absagen
                        </button>
                    </div>
                @endif
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Aktuell keine anstehenden Termine.
            </p>
        @endforelse
    </section>

</div>

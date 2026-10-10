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
            für die du nicht abgesagt hast, und deine Dienste – es ist derselbe
            Link wie unter „Mein Dienstplan“.
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

        @if($sources->isNotEmpty())
            <div class="mt-6 border-t border-slate-100 pt-4">
                <h4 class="text-sm font-semibold text-slate-900">
                    Weitere Kalender ins Abo übernehmen
                </h4>

                <p class="mt-1 text-sm text-slate-500">
                    Termine aus diesen Kalendern siehst du immer hier im Portal.
                    In dein Kalender-Abo kommen sie nur, wenn du sie einschaltest.
                </p>

                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($sources as $source)
                        @php($isSubscribed = in_array($source->sourceID, $subscribedSourceIDs, true))

                        <div wire:key="event-source-{{ $source->sourceID }}" class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm text-slate-700">
                                {{ $source->name }}
                            </span>

                            <button
                                type="button"
                                wire:click="toggleSourceSubscription({{ $source->sourceID }})"
                                aria-label="{{ $source->name }} ins Kalender-Abo übernehmen"
                                aria-pressed="{{ $isSubscribed ? 'true' : 'false' }}"
                                class="{{ $isSubscribed ? 'bg-emerald-600' : 'bg-slate-300' }} relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                            >
                                <span class="{{ $isSubscribed ? 'translate-x-6' : 'translate-x-1' }} inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
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
                    <div class="flex flex-wrap items-center gap-2 font-medium text-slate-900">
                        {{ $event->title }}

                        @if($event->source)
                            <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700">
                                {{ $event->source->name }}
                            </span>
                        @endif
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

        @if($hiddenEventsCount > 0)
            <div class="border-t border-slate-200 px-6 py-3 text-center">
                <button
                    type="button"
                    wire:click="showMoreEvents"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    Mehr anzeigen ({{ $hiddenEventsCount }} weitere)
                </button>
            </div>
        @endif
    </section>

</div>

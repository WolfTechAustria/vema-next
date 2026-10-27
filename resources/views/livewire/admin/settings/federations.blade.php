<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Verbände
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Verbände, die VEMA nutzen, können euren Verein einladen. Ein verbundener Verband sieht nur Kennzahlen
            (z. B. Anzahl der Mitglieder nach Alter und Geschlecht, Ein- und Austritte), keine Namen oder Kontaktdaten.
            Ihr erhaltet seine Mitteilungen, Termine und Dokumente. Austreten könnt ihr jederzeit.
        </p>
    </div>

    @foreach($invitations as $invitation)
        <section wire:key="invitation-{{ $invitation->id }}" class="max-w-2xl rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Einladung von {{ $invitation->federation->name }}</h3>

            <p class="mt-1 text-sm text-slate-600">
                Eingeladen am {{ $invitation->invited_at?->format('d.m.Y') }}. Mit dem Annehmen stimmt ihr zu, dass der Verband
                die Kennzahlen eures Vereins sehen darf.
            </p>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="accept({{ $invitation->id }})"
                    wire:confirm="Einladung von {{ $invitation->federation->name }} annehmen?"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                >
                    Annehmen
                </button>

                <button
                    type="button"
                    wire:click="decline({{ $invitation->id }})"
                    wire:confirm="Einladung ablehnen?"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Ablehnen
                </button>
            </div>
        </section>
    @endforeach

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="mb-3 font-semibold text-slate-900">Mitglied bei</h3>

        <ul class="divide-y divide-slate-100">
            @forelse($federations as $membership)
                <li wire:key="federation-{{ $membership->id }}" class="flex items-center justify-between gap-3 py-2">
                    <div>
                        <div class="font-medium text-slate-900">{{ $membership->federation->name }}</div>
                        <div class="text-xs text-slate-500">seit {{ $membership->responded_at?->format('d.m.Y') }}</div>
                    </div>

                    <button
                        type="button"
                        wire:click="leave({{ $membership->id }})"
                        wire:confirm="Wirklich aus {{ $membership->federation->name }} austreten? Der Verband sieht dann keine Kennzahlen mehr."
                        class="text-sm text-red-600 underline hover:text-red-800"
                    >
                        Austreten
                    </button>
                </li>
            @empty
                <li class="py-2 text-sm text-slate-500">Euer Verein ist mit keinem Verband verbunden.</li>
            @endforelse
        </ul>
    </section>

</div>

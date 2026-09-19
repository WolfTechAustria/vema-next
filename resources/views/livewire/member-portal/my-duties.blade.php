<div class="space-y-6">

    <!-- Kalender-Abo -->
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="font-semibold text-slate-900">
            Kalender-Abo
        </h3>

        <p class="mt-1 text-sm text-slate-500">
            Dieser Link kann in Google Kalender, Apple Kalender oder Outlook
            als Kalender-Abo hinzugefügt werden. Er aktualisiert sich
            automatisch, sobald sich deine Dienste ändern.
        </p>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                type="text"
                readonly
                value="{{ $icalUrl }}"
                onclick="this.select()"
                class="flex-1 rounded-md border-slate-300 bg-slate-50 text-sm"
            >

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

    <!-- Reminder -->
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-slate-900">
                    E-Mail-Erinnerung
                </h3>
                <p class="mt-1 text-sm text-slate-500">
                    Erhalte 2 Tage vor einem Dienst automatisch eine E-Mail.
                </p>
            </div>

            <button
                type="button"
                wire:click="toggleReminder"
                class="{{ $reminderEnabled ? 'bg-emerald-600' : 'bg-slate-300' }} relative inline-flex h-6 w-11 items-center rounded-full transition"
            >
                <span class="{{ $reminderEnabled ? 'translate-x-6' : 'translate-x-1' }} inline-block h-4 w-4 transform rounded-full bg-white transition"></span>
            </button>
        </div>
    </section>

    <!-- Anstehende Dienste -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Anstehende Dienste
            </h3>
        </div>

        @forelse ($upcoming as $assignment)
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div>
                    <div class="font-medium text-slate-900">
                        {{ $assignment->event->duty_name }}
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $assignment->event->duty_date->translatedFormat('l, d.m.Y') }}
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Aktuell keine anstehenden Dienste.
            </p>
        @endforelse
    </section>

    <!-- Vergangene Dienste -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Vergangene Dienste
            </h3>
        </div>

        @forelse ($past as $assignment)
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 last:border-b-0">
                <div>
                    <div class="font-medium text-slate-700">
                        {{ $assignment->event->duty_name }}
                    </div>
                    <div class="text-sm text-slate-500">
                        {{ $assignment->event->duty_date->translatedFormat('l, d.m.Y') }}
                    </div>
                </div>
            </div>
        @empty
            <p class="px-6 py-4 text-sm text-slate-500">
                Keine vergangenen Dienste.
            </p>
        @endforelse
    </section>

</div>

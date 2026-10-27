<div class="space-y-5">

    <a href="{{ route('central.admin.tenants') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-800">← Alle Vereine</a>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $tenant->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                <a href="{{ $tenant->url() }}/login" target="_blank" class="underline">{{ $tenant->host() }}</a>
                · {{ $tenant->accessLabel() }}
                · registriert am {{ $tenant->created_at->format('d.m.Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($tenant->provisioned_at && $tenant->isAccessible())
                <form method="POST" action="{{ route('central.admin.tenants.support-login', $tenant) }}" target="_blank">
                    @csrf
                    <button type="submit" class="rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800">
                        Als Verein anmelden
                    </button>
                </form>
            @endif

            @if($tenant->provisioned_at)
                <button
                    type="button"
                    wire:click="sendAccessLink"
                    wire:confirm="Neuen Link „Passwort festlegen“ an den Admin-Zugang des Vereins schicken?"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Zugangslink senden
                </button>
            @endif

            @if($tenant->status === \App\Enums\TenantStatus::Active)
                <button
                    type="button"
                    wire:click="suspend"
                    wire:confirm="{{ $tenant->name }} sperren? Niemand kann sich dann mehr anmelden."
                    class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                >
                    Sperren
                </button>
            @elseif($tenant->provisioned_at)
                <button type="button" wire:click="reactivate" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Freischalten
                </button>
            @elseif($tenant->email_verified_at)
                <button
                    type="button"
                    wire:click="provision"
                    wire:loading.attr="disabled"
                    wire:target="provision"
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="provision">Einrichtung nachholen</span>
                    <span wire:loading wire:target="provision">Wird eingerichtet …</span>
                </button>
            @else
                <button type="button" wire:click="resendVerification" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Bestätigungslink erneut senden
                </button>

                <button
                    type="button"
                    wire:click="verifyManually"
                    wire:confirm="E-Mail-Adresse als bestätigt markieren und {{ $tenant->name }} jetzt einrichten?"
                    wire:loading.attr="disabled"
                    wire:target="verifyManually"
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="verifyManually">Manuell bestätigen & einrichten</span>
                    <span wire:loading wire:target="verifyManually">Wird eingerichtet …</span>
                </button>
            @endif
        </div>
    </div>

    @foreach($limitWarnings as $warning)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $warning }}</div>
    @endforeach

    @if($tenant->email_verified_at === null)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Die E-Mail-Adresse ist noch nicht bestätigt.
            @if($verificationExpired)
                Der Bestätigungslink ist abgelaufen — die Registrierung wird beim nächsten Aufräumen gelöscht, außer du sendest einen neuen Link.
            @endif
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-semibold">Paket & Lizenz</h2>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Gebuchtes Paket</label>
                    <select wire:model="plan" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($plans as $planCase)
                            <option value="{{ $planCase->value }}">
                                {{ $planCase->label() }} ({{ $planCase->memberLimit() ? 'bis '.$planCase->memberLimit().' Mitglieder' : 'unbegrenzt' }})
                            </option>
                        @endforeach
                    </select>
                    @error('plan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Testphase bis</label>
                    <input type="date" wire:model="trial_ends_at" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('trial_ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Lizenz gültig bis</label>
                    <input type="date" wire:model="license_valid_until" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <p class="mt-1 text-xs text-slate-500">leer = unbegrenzt</p>
                    @error('license_valid_until') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="save" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Speichern
                </button>

                <span class="text-sm text-slate-400">Testphase:</span>

                @foreach([7, 14, 30] as $days)
                    <button type="button" wire:click="extendTrial({{ $days }})" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        +{{ $days }} Tage
                    </button>
                @endforeach

                @if($tenant->isOnTrial())
                    <button
                        type="button"
                        wire:click="endTrial"
                        wire:confirm="Testphase sofort beenden? Danach gilt das gebuchte Paket {{ $tenant->plan->label() }}."
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        Beenden
                    </button>
                @endif
            </div>

            <p class="mt-4 text-sm text-slate-600">
                Aktuell gilt: <strong>{{ $effectivePlan->label() }}</strong>
                @if($tenant->isOnTrial())
                    (Testphase, danach {{ $tenant->plan->label() }})
                @endif
                — bis {{ $effectivePlan->memberLimit() ?? 'unbegrenzt' }} Mitglieder,
                {{ $effectivePlan->staffLimit() ?? 'unbegrenzt' }} Vorstandszugänge.
            </p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold">Nutzung</h2>

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Gewünschtes Paket bei Registrierung</dt>
                    <dd>{{ $tenant->requested_plan?->label() ?? '–' }}</dd>
                </div>

                <div>
                    <dt class="text-slate-500">Datenbank</dt>
                    <dd class="font-mono text-xs">{{ basename($tenant->database) }}</dd>
                </div>

                @if($usage)
                    <div>
                        <dt class="text-slate-500">Aktive Mitglieder / Vorstandszugänge</dt>
                        <dd>{{ $usage['members'] }} / {{ $usage['staff'] }}</dd>
                    </div>
                @elseif($tenant->provisioned_at === null)
                    <div>
                        <dt class="text-slate-500">Einrichtung</dt>
                        <dd>{{ $tenant->email_verified_at ? 'E-Mail bestätigt, aber noch nicht eingerichtet' : 'E-Mail noch nicht bestätigt' }}</dd>
                    </div>
                @endif

                @if($tenant->provisioned_at)
                    <div>
                        <dt class="text-slate-500">Eingerichtet am</dt>
                        <dd>{{ $tenant->provisioned_at->format('d.m.Y') }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-semibold">Stammdaten & Notizen</h2>

            <form wire:submit="saveDetails" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Vereinsname</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kontaktperson</label>
                        <input type="text" wire:model="contact_name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('contact_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Kontakt-E-Mail</label>
                        <input type="email" wire:model="contact_email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('contact_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    Gilt nur für die Plattform (Übersicht, Erinnerungen). Name und Zugänge im Verein selbst bleiben unverändert.
                </p>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Interne Notizen</label>
                    <textarea wire:model="admin_notes" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="z. B. Absprachen, Rechnungsdaten, Ansprechpartner"></textarea>
                    @error('admin_notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Speichern
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold">Eigene Domains</h2>

            <ul class="mb-4 divide-y divide-slate-100 text-sm">
                @forelse($domains as $domain)
                    <li wire:key="domain-{{ $domain->id }}" class="flex items-center justify-between gap-2 py-2">
                        <span>
                            {{ $domain->domain }}
                            <span class="text-xs text-slate-500">({{ $domain->purpose === \App\Services\HomepageHosts::PURPOSE ? 'Homepage' : 'App' }})</span>
                        </span>
                        <button
                            type="button"
                            wire:click="removeDomain({{ $domain->id }})"
                            wire:confirm="{{ $domain->domain }} entfernen?"
                            class="text-xs text-red-700 hover:underline"
                        >
                            Entfernen
                        </button>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Keine eigenen Domains.</li>
                @endforelse
            </ul>

            <form wire:submit="addDomain" class="space-y-2">
                <input type="text" wire:model="newDomain" placeholder="www.mein-verein.at" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('newDomain') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <div class="flex gap-2">
                    <select wire:model="newDomainPurpose" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="app">App (Verwaltung)</option>
                        <option value="{{ \App\Services\HomepageHosts::PURPOSE }}">Homepage</option>
                    </select>
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Hinzufügen
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-semibold">Verlauf</h2>

            <ul class="divide-y divide-slate-100 text-sm">
                @forelse($events as $event)
                    <li wire:key="event-{{ $event->id }}" class="py-2">
                        <div class="flex flex-wrap justify-between gap-2">
                            <span class="font-medium">{{ $event->action }}</span>
                            <span class="text-xs text-slate-500">{{ $event->created_at?->format('d.m.Y H:i') }} · {{ $event->admin?->name ?? 'System' }}</span>
                        </div>
                        @if($event->details)
                            <div class="mt-0.5 text-xs text-slate-500">
                                @foreach($event->details as $key => $value)
                                    {{ $key }}: {{ is_scalar($value) ? $value : json_encode($value) }}@if(! $loop->last) · @endif
                                @endforeach
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Noch keine Einträge.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
            <h2 class="mb-2 font-semibold text-red-800">Verein löschen</h2>
            <p class="mb-4 text-sm text-slate-600">
                Löscht Datenbank, Testdaten, alle Dateien und Domains endgültig. Das lässt sich nicht rückgängig machen.
            </p>

            <form wire:submit="deleteTenant" wire:confirm="{{ $tenant->name }} wirklich endgültig löschen?" class="space-y-2">
                <label class="block text-sm text-slate-700">Zur Bestätigung <strong>{{ $tenant->slug }}</strong> eingeben:</label>
                <input type="text" wire:model="deleteConfirmation" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('deleteConfirmation') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <button
                    type="submit"
                    class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800"
                >
                    Endgültig löschen
                </button>
            </form>
        </section>

    </div>

</div>

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
            @endif
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="mb-4 font-semibold">Paket & Lizenz</h2>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Gebuchtes Paket</label>
                    <select wire:model="plan" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($plans as $planCase)
                            <option value="{{ $planCase->value }}">{{ $planCase->label() }}</option>
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

                <span class="text-sm text-slate-400">Testphase verlängern:</span>

                @foreach([7, 14, 30] as $days)
                    <button type="button" wire:click="extendTrial({{ $days }})" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        +{{ $days }} Tage
                    </button>
                @endforeach
            </div>

            <p class="mt-4 text-sm text-slate-600">
                Aktuell gilt: <strong>{{ $tenant->effectivePlan()->label() }}</strong>
                @if($tenant->isOnTrial())
                    (Testphase, danach {{ $tenant->plan->label() }})
                @endif
                — bis {{ $tenant->effectivePlan()->memberLimit() ?? 'unbegrenzt' }} Mitglieder,
                {{ $tenant->effectivePlan()->staffLimit() ?? 'unbegrenzt' }} Vorstandszugänge.
            </p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-semibold">Kontakt & Nutzung</h2>

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Kontakt</dt>
                    <dd>{{ $tenant->contact_name }}<br><a href="mailto:{{ $tenant->contact_email }}" class="underline">{{ $tenant->contact_email }}</a></dd>
                </div>

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
            </dl>
        </section>

    </div>

</div>

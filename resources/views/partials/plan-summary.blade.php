{{--
    Paket des Vereins (nur Plattform-Betrieb). Im Modus "single" gibt es
    keinen Verein-Datensatz und damit nichts anzuzeigen.
--}}
@php
    $tenant = app(\App\Services\TenantManager::class)->current();
    $entitlements = app(\App\Services\PlanEntitlements::class);
@endphp

@if($tenant)
    @php
        $effectivePlan = $tenant->effectivePlan();
        $memberLimit = $entitlements->memberLimit();
        $staffLimit = $entitlements->staffLimit();
    @endphp

    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold">Paket: {{ $effectivePlan->label() }}</h3>

                @if($tenant->isOnTrial())
                    <p class="mt-1 text-sm text-slate-600">
                        Testphase mit allen Funktionen bis <strong>{{ $tenant->trial_ends_at->format('d.m.Y') }}</strong>.
                        Danach gilt das Paket <strong>{{ $tenant->plan->label() }}</strong>.
                    </p>
                @elseif($tenant->license_valid_until)
                    <p class="mt-1 text-sm text-slate-600">
                        Lizenz gültig bis {{ $tenant->license_valid_until->format('d.m.Y') }}.
                    </p>
                @endif
            </div>

            <a
                href="{{ config('tenancy.website_url') }}/#preise"
                target="_blank"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Pakete ansehen
            </a>
        </div>

        <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-slate-50 px-4 py-3">
                <dt class="text-xs text-slate-500">Aktive Mitglieder</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">
                    {{ $entitlements->activeMemberCount() }}
                    <span class="text-sm font-normal text-slate-500">/ {{ $memberLimit ?? 'unbegrenzt' }}</span>
                </dd>
            </div>

            <div class="rounded-lg bg-slate-50 px-4 py-3">
                <dt class="text-xs text-slate-500">Vorstandszugänge</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">
                    {{ $entitlements->activeStaffCount() }}
                    <span class="text-sm font-normal text-slate-500">/ {{ $staffLimit ?? 'unbegrenzt' }}</span>
                </dd>
            </div>
        </dl>

        <ul class="mt-4 grid gap-1.5 text-sm sm:grid-cols-2">
            @foreach(\App\Enums\Feature::cases() as $feature)
                <li class="flex items-center gap-2 {{ $effectivePlan->includes($feature) ? 'text-slate-700' : 'text-slate-400' }}">
                    <span aria-hidden="true">{{ $effectivePlan->includes($feature) ? '✓' : '–' }}</span>
                    {{ $feature->label() }}
                    @unless($effectivePlan->includes($feature))
                        <span class="text-xs">(ab {{ \App\Enums\Plan::lowestWith($feature)->label() }})</span>
                    @endunless
                </li>
            @endforeach
        </ul>

    </section>
@endif

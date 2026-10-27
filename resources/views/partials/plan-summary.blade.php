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
        $emailLimit = $tenant->limit(\App\Enums\Limit::EmailsPerMonth);
        $storageLimit = $tenant->limit(\App\Enums\Limit::StorageMb);

        $usageTiles = [
            [
                'label' => 'Aktive Mitglieder',
                'used' => $entitlements->activeMemberCount(),
                'limit' => $tenant->limit(\App\Enums\Limit::Members),
                'format' => \App\Enums\Limit::Members,
            ],
            [
                'label' => 'Vorstandszugänge',
                'used' => $entitlements->activeStaffCount(),
                'limit' => $tenant->limit(\App\Enums\Limit::Staff),
                'format' => \App\Enums\Limit::Staff,
            ],
            [
                'label' => 'E-Mails im '.now()->locale('de')->translatedFormat('F'),
                'used' => app(\App\Services\MailQuota::class)->sentThisMonth($tenant),
                'limit' => $emailLimit,
                'format' => \App\Enums\Limit::EmailsPerMonth,
            ],
            [
                'label' => 'Speicher',
                'used' => round(app(\App\Services\StorageUsage::class)->currentUsedBytes() / 1024 / 1024, 1),
                'limit' => $storageLimit,
                'format' => \App\Enums\Limit::StorageMb,
            ],
        ];
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
            @foreach($usageTiles as $tile)
                @php
                    $percent = $tile['limit'] ? min(100, (int) round($tile['used'] / $tile['limit'] * 100)) : null;
                @endphp

                <div class="rounded-lg bg-slate-50 px-4 py-3">
                    <dt class="text-xs text-slate-500">{{ $tile['label'] }}</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        {{ $tile['format'] === \App\Enums\Limit::StorageMb ? \App\Enums\Limit::formatMegabytes($tile['used']) : $tile['format']->format((int) $tile['used']) }}
                        <span class="text-sm font-normal text-slate-500">/ {{ $tile['format']->format($tile['limit']) }}</span>
                    </dd>

                    @if($percent !== null)
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                            <div @class([
                                'h-full rounded-full',
                                'bg-emerald-500' => $percent < 80,
                                'bg-amber-500' => $percent >= 80 && $percent < 100,
                                'bg-red-500' => $percent >= 100,
                            ]) style="width: {{ $percent }}%"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </dl>

        <ul class="mt-4 grid gap-1.5 text-sm sm:grid-cols-2">
            @foreach(\App\Enums\Feature::cases() as $feature)
                <li class="flex items-center gap-2 {{ $tenant->allows($feature) ? 'text-slate-700' : 'text-slate-400' }}">
                    <span aria-hidden="true">{{ $tenant->allows($feature) ? '✓' : '–' }}</span>
                    {{ $feature->label() }}
                    @unless($tenant->allows($feature))
                        <span class="text-xs">(ab {{ \App\Enums\Plan::lowestWith($feature)->label() }})</span>
                    @endunless
                </li>
            @endforeach
        </ul>

    </section>
@endif

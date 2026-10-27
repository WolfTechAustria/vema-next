<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.federation-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Mitgliedsvereine
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Vereine, die VEMA nutzen, könnt ihr einladen. Erst wenn ein Verein die Einladung annimmt, seht ihr seine Kennzahlen
            und er erhält eure Mitteilungen, Termine und Dokumente.
        </p>
    </div>

    @if($isAdmin)
        <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="mb-3 font-semibold text-slate-900">Verein einladen</h3>

            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Name oder Adresse des Vereins (mind. 2 Zeichen)"
                class="w-full rounded-lg border border-slate-300 px-3 py-2"
            >

            @if(mb_strlen(trim($search)) >= 2)
                <ul class="mt-3 divide-y divide-slate-100">
                    @forelse($results as $club)
                        <li wire:key="result-{{ $club->id }}" class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <div class="font-medium text-slate-900">{{ $club->name }}</div>
                                <div class="text-xs text-slate-500">{{ $club->host() }}</div>
                            </div>

                            <button
                                type="button"
                                wire:click="invite({{ $club->id }})"
                                wire:confirm="{{ $club->name }} in den Verband einladen?"
                                class="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white"
                            >
                                Einladen
                            </button>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-slate-500">Kein passender Verein gefunden.</li>
                    @endforelse
                </ul>
            @endif
        </section>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Verein</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Seit</th>
                    @if($isAdmin)
                        <th class="px-4 py-3"></th>
                    @endif
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($memberships as $membership)
                    <tr wire:key="membership-{{ $membership->id }}">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $membership->club->name }}</div>
                            <div class="text-xs text-slate-500">{{ $membership->club->host() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $membership->status === \App\Enums\FederationMembershipStatus::Active,
                                'bg-amber-100 text-amber-800' => $membership->status === \App\Enums\FederationMembershipStatus::Invited,
                                'bg-slate-100 text-slate-600' => in_array($membership->status, [\App\Enums\FederationMembershipStatus::Declined, \App\Enums\FederationMembershipStatus::Left], true),
                            ])>
                                {{ $membership->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ ($membership->responded_at ?? $membership->invited_at)?->format('d.m.Y') }}
                        </td>
                        @if($isAdmin)
                            <td class="px-4 py-3 text-right">
                                @if($membership->status === \App\Enums\FederationMembershipStatus::Invited)
                                    <button type="button" wire:click="remove({{ $membership->id }})" wire:confirm="Einladung zurückziehen?" class="text-sm text-slate-500 underline hover:text-slate-800">
                                        Zurückziehen
                                    </button>
                                @elseif($membership->status === \App\Enums\FederationMembershipStatus::Active)
                                    <button type="button" wire:click="remove({{ $membership->id }})" wire:confirm="{{ $membership->club->name }} aus dem Verband entfernen?" class="text-sm text-red-600 underline hover:text-red-800">
                                        Entfernen
                                    </button>
                                @else
                                    <button type="button" wire:click="remove({{ $membership->id }})" class="text-sm text-slate-500 underline hover:text-slate-800">
                                        Aus Liste löschen
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Noch keine Vereine eingeladen.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

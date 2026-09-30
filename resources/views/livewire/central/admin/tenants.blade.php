<div class="space-y-5">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Vereine</h1>
            <p class="mt-1 text-sm text-slate-500">
                @foreach($statuses as $statusCase)
                    {{ $statusCase->label() }}: {{ $counts[$statusCase->value] ?? 0 }}@if(! $loop->last) · @endif
                @endforeach
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Name, Adresse oder E-Mail"
                class="w-64 max-w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >

            <select wire:model.live="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Alle Status</option>
                @foreach($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Verein</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Paket</th>
                    <th class="px-4 py-3">Test bis</th>
                    <th class="px-4 py-3">Lizenz bis</th>
                    <th class="px-4 py-3">Registriert</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($tenants as $tenant)
                    <tr wire:key="tenant-{{ $tenant->id }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('central.admin.tenants.show', $tenant) }}" wire:navigate class="font-medium text-slate-900 hover:underline">
                                {{ $tenant->name }}
                            </a>
                            <div class="text-xs text-slate-500">{{ $tenant->host() }} · {{ $tenant->contact_email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => $tenant->isAccessible(),
                                'bg-amber-100 text-amber-800' => $tenant->status === \App\Enums\TenantStatus::Pending,
                                'bg-red-100 text-red-800' => ! $tenant->isAccessible() && $tenant->status !== \App\Enums\TenantStatus::Pending,
                            ])>
                                {{ $tenant->accessLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            {{ $tenant->plan->label() }}
                            @if($tenant->isOnTrial())
                                <span class="text-xs text-sky-700">(Test: {{ $tenant->effectivePlan()->label() }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $tenant->trial_ends_at?->format('d.m.Y') ?? '–' }}</td>
                        <td class="px-4 py-3">{{ $tenant->license_valid_until?->format('d.m.Y') ?? 'unbegrenzt' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $tenant->created_at->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">Keine Vereine gefunden.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tenants->links() }}

</div>

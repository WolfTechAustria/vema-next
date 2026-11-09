<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <a href="{{ route('members.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Mitglieder
            </a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">Online-Anmeldungen</h2>

            <p class="mt-1 text-sm text-slate-500">
                Über das Anmeldeformular eingereichte Beitrittserklärungen. Freigegebene Anmeldungen werden zu Mitgliedern.
            </p>
        </div>

        @if(auth()->user()?->hasRole('admin'))
            <a href="{{ route('admin.settings.application') }}" wire:navigate class="shrink-0 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Formular einrichten
            </a>
        @endif
    </div>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Name oder E-Mail …"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 sm:max-w-sm"
            >

            <select wire:model.live="statusFilter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}">
                        {{ $status->label() }}{{ $status === \App\Enums\MemberApplicationStatus::Pending ? ' ('.$pendingCount.')' : '' }}
                    </option>
                @endforeach
                <option value="all">Alle</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Eingereicht</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Mitgliedsart</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                @forelse($applications as $application)
                    <tr wire:key="application-{{ $application->applicationID }}" class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('members.applications.show', $application) }}" wire:navigate class="font-medium text-slate-900 hover:underline">
                                {{ $application->full_name }}
                            </a>
                            <div class="text-xs text-slate-500">{{ $application->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $application->applied_on->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $application->membership_type ?? '–' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $application->status->badgeClasses() }}">
                                {{ $application->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">
                            Keine Anmeldungen gefunden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($applications->hasPages())
            <div class="border-t border-slate-200 p-4">
                {{ $applications->links() }}
            </div>
        @endif

    </section>

</div>

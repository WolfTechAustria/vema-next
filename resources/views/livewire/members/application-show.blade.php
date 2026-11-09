<div class="space-y-6">

    @include('partials.flash-messages')

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('members.applications.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Online-Anmeldungen
            </a>

            <h2 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-bold tracking-tight">
                {{ $application->full_name }}
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $application->status->badgeClasses() }}">
                    {{ $application->status->label() }}
                </span>
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Eingereicht am {{ $application->created_at->timezone(config('app.club_timezone'))->format('d.m.Y, H:i') }} Uhr
                @if($application->verified_at)
                    · E-Mail bestätigt
                @endif
            </p>
        </div>

        <a href="{{ route('members.applications.pdf', $application) }}" target="_blank" class="shrink-0 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Beitrittserklärung (PDF)
        </a>
    </div>

    @if($duplicates->isNotEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
            <p class="font-semibold">Möglicherweise bereits erfasst:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach($duplicates as $duplicate)
                    <li>
                        <a href="{{ route('members.show', $duplicate) }}" wire:navigate class="underline">{{ $duplicate->full_name }}</a>
                        {{ $duplicate->active ? '' : '(ausgetreten)' }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-amber-800">Bei ehemaligen Mitgliedern ggf. den Wiedereintritt beim Mitglied erfassen und diese Anmeldung ablehnen.</p>
        </div>
    @endif

    @if($application->status === \App\Enums\MemberApplicationStatus::Approved && $application->member)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
            Freigegeben am {{ $application->processed_at?->timezone(config('app.club_timezone'))->format('d.m.Y') }}
            {{ $application->processor ? 'von '.$application->processor->name : '' }} –
            <a href="{{ route('members.show', $application->member) }}" wire:navigate class="font-semibold underline">zum Mitglied</a>
        </div>
    @elseif($application->status === \App\Enums\MemberApplicationStatus::Rejected)
        <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-900">
            Abgelehnt am {{ $application->processed_at?->timezone(config('app.club_timezone'))->format('d.m.Y') }}
            {{ $application->processor ? 'von '.$application->processor->name : '' }}
            @if($application->rejection_note)
                <div class="mt-1 whitespace-pre-line">{{ $application->rejection_note }}</div>
            @endif
        </div>
    @elseif($application->status === \App\Enums\MemberApplicationStatus::Unverified)
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-700">
            Die E-Mail-Adresse wurde noch nicht bestätigt. Unbestätigte Anmeldungen werden nach
            {{ \App\Services\MemberApplications::PRUNE_UNVERIFIED_AFTER_DAYS }} Tagen automatisch gelöscht.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Angaben</h3>

                @if($isOpen && ! $editing)
                    <button type="button" wire:click="edit" class="text-sm font-medium text-slate-600 hover:text-slate-900">Korrigieren</button>
                @endif
            </div>

            @if($editing)
                <div class="space-y-4 p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Anrede</label>
                            <select wire:model="gender" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">–</option>
                                <option value="w">Frau</option>
                                <option value="m">Herr</option>
                            </select>
                            @error('gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Vorname</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nachname</label>
                            <input type="text" wire:model="surname" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('surname') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Geburtsdatum</label>
                            <input type="date" wire:model="dateOfBirth" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('dateOfBirth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Straße</label>
                            <input type="text" wire:model="street" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('street') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">PLZ</label>
                            <input type="text" wire:model="zip" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('zip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Ort</label>
                            <input type="text" wire:model="city" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">E-Mail</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Telefon</label>
                            <input type="text" wire:model="phone" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Kennzeichnung</label>
                            <select wire:model="membershipKind" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">–</option>
                                @foreach($kinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelEdit" class="rounded-lg border border-slate-300 px-4 py-2">Abbrechen</button>
                        <button type="button" wire:click="save" class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Speichern</button>
                    </div>
                </div>
            @else
                <dl class="divide-y divide-slate-100">
                    @foreach($rows as $label => $value)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="col-span-2 text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach

                    <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                        <dt class="text-slate-500">Mitgliedsart</dt>
                        <dd class="col-span-2 text-slate-900">
                            {{ $application->membership_type ?? '–' }}
                            @if($application->membership_kind)
                                <span class="text-slate-500">({{ $application->membership_kind->label() }})</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                        <dt class="text-slate-500">Beitrittsdatum</dt>
                        <dd class="col-span-2 text-slate-900">{{ $application->applied_on->format('d.m.Y') }}</dd>
                    </div>

                    @if($application->place)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                            <dt class="text-slate-500">Ort</dt>
                            <dd class="col-span-2 text-slate-900">{{ $application->place }}</dd>
                        </div>
                    @endif

                    @if($application->guardian_name)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                            <dt class="text-slate-500">Erziehungsberechtigte(r)</dt>
                            <dd class="col-span-2 text-slate-900">{{ $application->guardian_name }}</dd>
                        </div>
                    @endif

                    @foreach($application->consents ?? [] as $consent)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                            <dt class="text-slate-500">Zustimmung</dt>
                            <dd class="col-span-2 {{ ($consent['accepted'] ?? false) ? 'text-slate-900' : 'text-slate-400 line-through' }}">
                                {{ ($consent['accepted'] ?? false) ? '✓' : '✗' }} {{ $consent['text'] ?? '' }}
                            </dd>
                        </div>
                    @endforeach

                    @if($signature)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 text-sm">
                            <dt class="text-slate-500">Unterschrift</dt>
                            <dd class="col-span-2"><img src="{{ $signature }}" alt="Unterschrift" class="max-h-24 rounded border border-slate-200 bg-white"></dd>
                        </div>
                    @endif
                </dl>
            @endif
        </section>

        <div class="space-y-6">
            @if($isOpen)
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-slate-900">Freigeben</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Legt das Mitglied mit Beitrittsdatum {{ $application->applied_on->format('d.m.Y') }} an und speichert die Beitrittserklärung in seinen Dokumenten.
                    </p>

                    <label class="mt-4 flex items-center gap-3 text-sm text-slate-700">
                        <input type="checkbox" wire:model="sendWelcomeMail" class="rounded border-slate-300">
                        Willkommens-E-Mail senden
                    </label>

                    @error('approve') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if(! $editing)
                        @error('gender') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif

                    <button
                        type="button"
                        wire:click="approve"
                        wire:confirm="„{{ $application->full_name }}“ als Mitglied aufnehmen?"
                        @disabled($editing)
                        class="mt-4 w-full rounded-lg bg-emerald-700 px-4 py-2.5 font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                    >
                        Als Mitglied aufnehmen
                    </button>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    @if($showReject)
                        <h3 class="font-semibold text-slate-900">Ablehnen</h3>

                        <label class="mb-1 mt-3 block text-sm font-medium">Begründung <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea wire:model="rejectionNote" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('rejectionNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                        <label class="mt-3 flex items-center gap-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="notifyApplicant" class="rounded border-slate-300">
                            Antragsteller per E-Mail informieren
                        </label>

                        <div class="mt-4 flex gap-2">
                            <button type="button" wire:click="$set('showReject', false)" class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</button>
                            <button type="button" wire:click="reject" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Ablehnen</button>
                        </div>
                    @else
                        <button type="button" wire:click="$set('showReject', true)" class="w-full rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                            Anmeldung ablehnen …
                        </button>
                    @endif
                </section>
            @endif

            <button
                type="button"
                wire:click="delete"
                wire:confirm="Anmeldung von „{{ $application->full_name }}“ endgültig löschen?"
                class="text-sm font-medium text-slate-500 hover:text-red-700"
            >
                Anmeldung löschen
            </button>
        </div>

    </div>

</div>

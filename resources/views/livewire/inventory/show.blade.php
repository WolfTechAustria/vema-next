<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Alle Materialien
            </a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ $item->name }}</h2>

            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                @if($item->inventory_number)
                    <span>Nr. {{ $item->inventory_number }}</span>
                @endif
                @if($item->category)
                    <span>· {{ $item->category->name }}</span>
                @endif
                <span class="inline-flex rounded-full px-3 py-0.5 text-xs font-semibold {{ $item->condition->badgeClasses() }}">{{ $item->condition->label() }}</span>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('inventory.edit', $item) }}" wire:navigate class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Bearbeiten
            </a>
            <button
                type="button"
                wire:click="delete"
                wire:confirm="„{{ $item->name }}“ samt Ausgabe- und Prüfhistorie löschen? Ausgeschiedenes Material besser auf „Ausgeschieden“ setzen."
                class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
            >
                Löschen
            </button>
        </div>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            {{-- Ausgabe --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">Ausgabe</h3>

                    @if($currentLoan)
                        <button type="button" wire:click="openReturnForm" class="text-sm font-medium text-slate-700 hover:underline">Rückgabe erfassen</button>
                    @elseif($item->condition !== \App\Enums\InventoryCondition::Retired)
                        <button type="button" wire:click="openLoanForm" class="text-sm font-medium text-slate-700 hover:underline">+ An Mitglied ausgeben</button>
                    @endif
                </div>

                <div class="space-y-4 px-6 py-4">
                    @if($currentLoan)
                        <div class="rounded-lg {{ $currentLoan->isOverdue() ? 'bg-red-50' : 'bg-slate-50' }} px-4 py-3 text-sm">
                            Ausgegeben an
                            @if($currentLoan->member)
                                <a href="{{ route('members.show', $currentLoan->member) }}" wire:navigate class="font-semibold text-slate-900 hover:underline">{{ $currentLoan->member->full_name }}</a>
                            @else
                                <span class="font-semibold">Mitglied #{{ $currentLoan->memberID }}</span>
                            @endif
                            seit {{ $currentLoan->issued_on->format('d.m.Y') }}
                            @if($currentLoan->due_on)
                                · Rückgabe bis <span class="{{ $currentLoan->isOverdue() ? 'font-semibold text-red-700' : '' }}">{{ $currentLoan->due_on->format('d.m.Y') }}</span>
                            @endif
                            @if($currentLoan->note)
                                <div class="mt-1 text-slate-500">{{ $currentLoan->note }}</div>
                            @endif
                        </div>
                    @elseif(! $showLoanForm)
                        <p class="text-sm text-slate-500">Derzeit nicht ausgegeben.</p>
                    @endif

                    @if($showLoanForm && ! $currentLoan)
                        <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Mitglied</label>
                                @if($selectedMember)
                                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <span class="font-medium">{{ $selectedMember->full_name }}</span>
                                        <button type="button" wire:click="$set('loanMemberID', null)" class="text-slate-500 hover:underline">ändern</button>
                                    </div>
                                @else
                                    <input type="search" wire:model.live.debounce.300ms="loanMemberSearch" placeholder="Name eingeben …" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @if($memberMatches->isNotEmpty())
                                        <ul class="mt-1 divide-y divide-slate-100 rounded-lg border border-slate-200">
                                            @foreach($memberMatches as $match)
                                                <li wire:key="match-{{ $match->memberID }}">
                                                    <button type="button" wire:click="selectLoanMember({{ $match->memberID }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $match->full_name }}</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                                @error('loanMemberID') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Ausgegeben am</label>
                                    <input type="date" wire:model="loanIssuedOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @error('loanIssuedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Rückgabe bis <span class="font-normal text-slate-400">(optional)</span></label>
                                    <input type="date" wire:model="loanDueOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @error('loanDueOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Notiz <span class="font-normal text-slate-400">(optional)</span></label>
                                <input type="text" wire:model="loanNote" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('loanNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('showLoanForm', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</button>
                                <button type="button" wire:click="issue" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Ausgeben</button>
                            </div>
                        </div>
                    @endif

                    @if($showReturnForm && $currentLoan)
                        <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Zurückgegeben am</label>
                                    <input type="date" wire:model="returnedOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @error('returnedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Zustand</label>
                                    <select wire:model="returnCondition" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                        @foreach($conditions as $conditionOption)
                                            <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('showReturnForm', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</button>
                                <button type="button" wire:click="returnItem" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Rückgabe speichern</button>
                            </div>
                        </div>
                    @endif

                    @if($loanHistory->isNotEmpty())
                        <div>
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Frühere Ausgaben</h4>
                            <ul class="divide-y divide-slate-100 text-sm">
                                @foreach($loanHistory as $loan)
                                    <li wire:key="loan-{{ $loan->loanID }}" class="flex flex-wrap justify-between gap-2 py-2">
                                        <span class="text-slate-700">{{ $loan->member?->full_name ?? 'Mitglied #'.$loan->memberID }}</span>
                                        <span class="text-slate-500">{{ $loan->issued_on->format('d.m.Y') }} – {{ $loan->returned_on->format('d.m.Y') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Prüfungen --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 class="font-semibold text-slate-900">Prüfung / Wartung</h3>
                        <p class="text-xs text-slate-500">
                            @if($item->next_inspection_on)
                                Nächste Prüfung:
                                <span class="{{ $item->isInspectionOverdue() ? 'font-semibold text-red-700' : ($item->isInspectionDue() ? 'font-semibold text-amber-700' : '') }}">{{ $item->next_inspection_on->format('d.m.Y') }}</span>
                            @else
                                Kein Prüftermin festgelegt
                            @endif
                            @if($item->inspection_interval_months)
                                · alle {{ $item->inspection_interval_months }} Monate
                            @endif
                        </p>
                    </div>

                    <button type="button" wire:click="openInspectionForm" class="text-sm font-medium text-slate-700 hover:underline">+ Prüfung erfassen</button>
                </div>

                <div class="space-y-4 px-6 py-4">
                    @if($showInspectionForm)
                        <div class="space-y-3 rounded-lg border border-slate-200 p-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Geprüft am</label>
                                    <input type="date" wire:model="inspectedOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @error('inspectedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Ergebnis</label>
                                    <select wire:model="inspectionResult" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                        @foreach($inspectionResults as $resultOption)
                                            <option value="{{ $resultOption->value }}">{{ $resultOption->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Nächste Prüfung</label>
                                    <input type="date" wire:model="inspectionNextOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $item->inspection_interval_months ? 'Leer lassen: automatisch in '.$item->inspection_interval_months.' Monaten.' : 'Leer lassen: kein weiterer Termin.' }}
                                    </p>
                                    @error('inspectionNextOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Notiz</label>
                                    <input type="text" wire:model="inspectionNote" placeholder="z. B. Service bei Fa. …" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @error('inspectionNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button type="button" wire:click="$set('showInspectionForm', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</button>
                                <button type="button" wire:click="recordInspection" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Speichern</button>
                            </div>
                        </div>
                    @endif

                    @forelse($item->inspections as $inspection)
                        <div wire:key="inspection-{{ $inspection->inspectionID }}" class="flex flex-wrap justify-between gap-2 text-sm">
                            <div>
                                <span class="font-medium text-slate-800">{{ $inspection->inspected_on->format('d.m.Y') }}</span>
                                <span class="{{ $inspection->result === \App\Enums\InventoryInspectionResult::Ok ? 'text-emerald-700' : 'text-red-700' }}">· {{ $inspection->result->label() }}</span>
                                @if($inspection->note)
                                    <span class="text-slate-500">· {{ $inspection->note }}</span>
                                @endif
                            </div>
                            @if($inspection->user)
                                <span class="text-xs text-slate-400">{{ $inspection->user->username }}</span>
                            @endif
                        </div>
                    @empty
                        @unless($showInspectionForm)
                            <p class="text-sm text-slate-500">Noch keine Prüfung erfasst.</p>
                        @endunless
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Stammdaten --}}
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="w-full rounded-lg object-cover">
            @endif

            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-slate-500">Lagerort</dt>
                    <dd class="font-medium text-slate-900">{{ $item->location ?? '–' }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Anschaffung</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $item->purchased_on?->format('d.m.Y') ?? '–' }}
                        @if($item->purchase_price !== null)
                            · {{ number_format((float) $item->purchase_price, 2, ',', '.') }} €
                        @endif
                    </dd>
                </div>
                @if($item->note)
                    <div>
                        <dt class="text-slate-500">Notiz</dt>
                        <dd class="whitespace-pre-line text-slate-900">{{ $item->note }}</dd>
                    </div>
                @endif
            </dl>
        </section>

    </div>

</div>

<div
    class="space-y-6"
    x-data="scanFeedback"
    x-on:scan-result.window="signal($event.detail); $nextTick(() => $refs.codeInput?.focus())"
>

    <div>
        <a href="{{ route('inventory.index') }}" wire:navigate class="text-sm font-medium text-slate-500 hover:text-slate-800">
            ← Alle Materialien
        </a>

        <h2 class="mt-1 text-2xl font-bold tracking-tight">Schnellausgabe</h2>

        <p class="mt-1 text-sm text-slate-500">
            Mitglied wählen, dann Material scannen – mit Barcode- bzw. NFC-Leser am PC oder mit der Handykamera.
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        <div class="space-y-6 lg:col-span-2">

            {{-- Schritt 1: Mitglied --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-slate-900">1. Mitglied</h3>

                @if($member)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3">
                        <span class="text-lg font-semibold text-slate-900">{{ $member->full_name }}</span>
                        <button type="button" wire:click="changeMember" class="text-sm text-slate-600 hover:underline">Anderes Mitglied</button>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Ausgegeben am</label>
                            <input type="date" wire:model.blur="issuedOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('issuedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Rückgabe bis <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="date" wire:model.blur="dueOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('dueOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @else
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="memberSearch"
                        placeholder="Name eingeben …"
                        autofocus
                        class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    @if($memberMatches->isNotEmpty())
                        <ul class="mt-1 divide-y divide-slate-100 rounded-lg border border-slate-200">
                            @foreach($memberMatches as $match)
                                <li wire:key="match-{{ $match->memberID }}">
                                    <button type="button" wire:click="selectMember({{ $match->memberID }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50">{{ $match->full_name }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>

            {{-- Schritt 2: Scannen --}}
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm {{ $member ? '' : 'opacity-50' }}">
                <h3 class="font-semibold text-slate-900">2. Material scannen</h3>

                @if($member)
                    <input
                        type="text"
                        x-ref="codeInput"
                        wire:model="code"
                        wire:keydown.enter.prevent="scan"
                        x-init="$el.focus()"
                        placeholder="Barcode / NFC scannen oder Nummer eintippen + Enter"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        class="mt-3 w-full rounded-lg border-2 border-slate-300 px-4 py-3 font-mono text-lg focus:border-slate-900 focus:outline-none"
                    >

                    @include('livewire.inventory.partials.camera-scanner', [
                        'onCode' => '$wire.scan(code)',
                        'label' => 'Mit Kamera scannen',
                        'class' => 'mt-3',
                    ])
                @else
                    <p class="mt-2 text-sm text-slate-500">Bitte zuerst ein Mitglied wählen.</p>
                @endif

                @if($lastResult)
                    @php
                        $resultClasses = match ($lastResult['status']) {
                            'issued', 'returned' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'already' => 'border-amber-200 bg-amber-50 text-amber-800',
                            default => 'border-red-200 bg-red-50 text-red-800',
                        };
                    @endphp

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $resultClasses }}">
                        <span class="font-medium">{{ $lastResult['message'] }}</span>

                        @if($lastResult['status'] === 'already' && $lastResult['loanID'])
                            <button type="button" wire:click="returnItem({{ $lastResult['loanID'] }})" class="rounded-lg bg-white px-3 py-1.5 font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                                Zurücknehmen
                            </button>
                        @elseif($lastResult['status'] === 'not_found' && $lastResult['code'] !== '')
                            <a href="{{ route('inventory.create', ['code' => $lastResult['code']]) }}" target="_blank" class="font-semibold underline">
                                Material anlegen
                            </a>
                        @endif
                    </div>
                @endif

                @if($historyEntries->isNotEmpty())
                    <div class="mt-4">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">In dieser Sitzung</h4>
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach($historyEntries as $index => $entry)
                                <li wire:key="history-{{ $index }}-{{ $entry['itemID'] }}" class="flex justify-between gap-2 py-2">
                                    <span class="text-slate-800">{{ $entry['item']->label() }}</span>
                                    <span class="{{ $entry['action'] === 'issued' ? 'text-emerald-700' : 'text-slate-500' }}">
                                        {{ $entry['action'] === 'issued' ? 'ausgegeben' : 'zurückgenommen' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        {{-- Beim Mitglied --}}
        <section class="h-fit rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="font-semibold text-slate-900">Derzeit beim Mitglied</h3>
            </div>

            @forelse($openLoans as $loan)
                <div wire:key="open-loan-{{ $loan->loanID }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-3 text-sm last:border-b-0">
                    <div>
                        <div class="font-medium text-slate-900">{{ $loan->item->label() }}</div>
                        <div class="text-slate-500">seit {{ $loan->issued_on->format('d.m.Y') }}</div>
                    </div>
                    <button
                        type="button"
                        wire:click="returnItem({{ $loan->loanID }})"
                        wire:confirm="Rückgabe von „{{ $loan->item->name }}“ mit heutigem Datum erfassen?"
                        class="text-xs font-semibold text-slate-500 hover:text-slate-800"
                    >
                        Zurücknehmen
                    </button>
                </div>
            @empty
                <p class="px-6 py-4 text-sm text-slate-500">{{ $member ? 'Kein Material.' : '–' }}</p>
            @endforelse
        </section>

    </div>

</div>

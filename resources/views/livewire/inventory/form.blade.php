<div class="space-y-6">

    <div>
        <a
            href="{{ $item ? route('inventory.show', $item) : route('inventory.index') }}"
            wire:navigate
            class="text-sm font-medium text-slate-500 hover:text-slate-800"
        >
            ← {{ $item ? 'Zurück zum Material' : 'Alle Materialien' }}
        </a>

        <h2 class="mt-1 text-2xl font-bold tracking-tight">
            {{ $item ? 'Material bearbeiten' : 'Neues Material' }}
        </h2>
    </div>

    @include('partials.flash-messages')

    <form wire:submit="save" class="grid gap-6 lg:grid-cols-3">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Bezeichnung</label>
                    <input type="text" wire:model="name" placeholder="z. B. Tracht Herren Gr. 52" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Kategorie</label>
                    <input type="text" wire:model="category" list="inventory-categories" placeholder="z. B. Tracht" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <datalist id="inventory-categories">
                        @foreach($categories as $categoryName)
                            <option value="{{ $categoryName }}"></option>
                        @endforeach
                    </datalist>
                    @error('category') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Inventarnummer</label>
                    <input type="text" wire:model="inventoryNumber" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('inventoryNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">
                        Barcode / NFC
                        <span class="font-normal text-slate-400">(optional, für die Schnellausgabe per Scan)</span>
                    </label>
                    <input
                        type="text"
                        wire:model="code"
                        x-on:keydown.enter.prevent
                        placeholder="Mit Leser scannen oder eintippen"
                        autocomplete="off"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono"
                    >
                    @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    @include('livewire.inventory.partials.camera-scanner', [
                        'onCode' => "\$wire.set('code', code)",
                        'once' => true,
                        'label' => 'Mit Kamera scannen',
                        'class' => 'mt-2',
                    ])
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Lagerort</label>
                    <input type="text" wire:model="location" list="inventory-locations" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <datalist id="inventory-locations">
                        @foreach($locations as $locationName)
                            <option value="{{ $locationName }}"></option>
                        @endforeach
                    </datalist>
                    @error('location') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Zustand</label>
                    <select wire:model="condition" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($conditions as $conditionOption)
                            <option value="{{ $conditionOption->value }}">{{ $conditionOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('condition') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Anschaffung</label>
                    <input type="date" wire:model="purchasedOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('purchasedOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Anschaffungspreis (€)</label>
                    <input type="text" inputmode="decimal" wire:model="purchasePrice" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('purchasePrice') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Notiz</label>
                    <textarea wire:model="note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                    @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h3 class="font-semibold text-slate-900">Prüfung / Wartung</h3>
                <p class="mt-1 text-xs text-slate-500">Optional. Mit Intervall wird nach jeder erfassten Prüfung der nächste Termin berechnet. Admins und Zeugwarte werden an fällige Prüfungen erinnert.</p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Intervall (Monate)</label>
                        <input type="number" min="1" max="240" wire:model="inspectionIntervalMonths" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('inspectionIntervalMonths') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Nächste Prüfung</label>
                        <input type="date" wire:model="nextInspectionOn" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('nextInspectionOn') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Foto</h3>

            @if($photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="" class="w-full rounded-lg object-cover">
            @elseif($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="w-full rounded-lg object-cover">
                <button type="button" wire:click="removePhoto" wire:confirm="Foto entfernen?" class="text-sm text-red-700 hover:underline">Foto entfernen</button>
            @else
                <div class="flex aspect-[4/3] w-full items-center justify-center rounded-lg bg-slate-50 text-sm text-slate-400">Kein Foto</div>
            @endif

            <x-upload-guard :max-kb="$photoMaxKb">
                <label class="inline-block cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ $photoUrl || $photo ? 'Foto ersetzen' : 'Foto auswählen' }}
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="hidden">
                </label>

                <span wire:loading wire:target="photo" class="ml-2 text-xs text-slate-500">Wird hochgeladen …</span>
            </x-upload-guard>

            <x-upload-error field="photo" :max-kb="$photoMaxKb" />
        </section>

        <div class="flex justify-end gap-2 lg:col-span-3">
            <a href="{{ $item ? route('inventory.show', $item) : route('inventory.index') }}" wire:navigate class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Abbrechen</a>
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Speichern</button>
        </div>
    </form>

</div>

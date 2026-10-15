<div
    x-data="{
        preview: null,
        dragging: false,
        uploading: false,
        progress: 0,
        dropFiles(files) {
            this.dragging = false;

            if (! files.length) {
                return;
            }

            this.uploading = true;

            $wire.uploadMultiple(
                'newFiles',
                files,
                () => { this.uploading = false; this.progress = 0; },
                () => { this.uploading = false; this.progress = 0; },
                (event) => { this.progress = event.detail.progress; },
            );
        },
    }"
    @keydown.escape.window="preview = null"
    class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
>

    @if($statusMessage)
        <div
            wire:key="files-status-{{ md5($statusMessage.microtime()) }}"
            x-data="{ show: true }"
            x-init="setTimeout(() => { show = false; $wire.set('statusMessage', null) }, 4000)"
            x-show="show"
            x-transition.opacity
            role="status"
            class="border-b border-emerald-200 bg-emerald-50 px-6 py-2 text-sm font-medium text-emerald-800"
        >
            {{ $statusMessage }}
        </div>
    @endif

    <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">

        <div class="flex items-center gap-2">
            <h3 class="font-semibold text-slate-900">Dateien</h3>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                {{ $folderCounts['all'] }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="In {{ \App\Livewire\Members\Files::FOLDERS[$folder] }} suchen …"
                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm sm:w-56"
            >

            <div class="flex shrink-0 overflow-hidden rounded-lg border border-slate-300">
                <button
                    type="button"
                    wire:click="$set('layout', 'list')"
                    title="Liste"
                    aria-label="Listenansicht"
                    class="px-2.5 py-1.5 {{ $layout === 'list' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <button
                    type="button"
                    wire:click="$set('layout', 'grid')"
                    title="Kacheln"
                    aria-label="Kachelansicht"
                    class="border-l border-slate-300 px-2.5 py-1.5 {{ $layout === 'grid' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linejoin="round" d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z" /></svg>
                </button>
            </div>
        </div>

    </div>

    <div class="grid md:grid-cols-[13rem_1fr]">

        {{-- Ordner --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-slate-50 p-3 md:flex-col md:border-b-0 md:border-r">
            @foreach(\App\Livewire\Members\Files::FOLDERS as $folderKey => $folderLabel)
                <button
                    type="button"
                    wire:click="openFolder('{{ $folderKey }}')"
                    class="flex shrink-0 items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition
                        {{ $folder === $folderKey ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white hover:text-slate-900' }}"
                >
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 {{ $folder === $folderKey ? 'text-amber-500' : 'text-slate-400' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M3 6.75A1.75 1.75 0 0 1 4.75 5h4.19c.46 0 .9.18 1.23.51l1.32 1.32c.33.33.77.51 1.23.51h6.53A1.75 1.75 0 0 1 21 9.09v8.16A1.75 1.75 0 0 1 19.25 19H4.75A1.75 1.75 0 0 1 3 17.25V6.75Z" /></svg>
                        {{ $folderLabel }}
                    </span>
                    <span class="text-xs text-slate-400">{{ $folderCounts[$folderKey] }}</span>
                </button>
            @endforeach
        </nav>

        <div class="min-w-0 p-4 sm:p-6">

            {{-- Hochladen (landet im Ordner „Dokumente“) --}}
            @if(in_array($folder, ['all', 'documents'], true))
                <div
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dropFiles($event.dataTransfer.files)"
                    class="mb-5 rounded-xl border-2 border-dashed p-4 transition"
                    :class="dragging ? 'border-slate-500 bg-slate-50' : 'border-slate-200'"
                >
                    <div class="flex flex-col items-center gap-2 text-center sm:flex-row sm:text-left">
                        <svg class="h-8 w-8 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>

                        <div class="flex-1 text-sm text-slate-600">
                            Dateien hierher ziehen oder
                            <label class="cursor-pointer font-semibold text-slate-900 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-900">
                                auswählen
                                <input type="file" wire:model="newFiles" multiple class="sr-only">
                            </label>
                            <div class="text-xs text-slate-400">
                                PDF, Bilder, Word, Excel · max. 10 MB je Datei
                            </div>
                        </div>
                    </div>

                    <div x-show="uploading" x-cloak class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-slate-900 transition-all" :style="`width: ${progress}%`"></div>
                    </div>

                    <div wire:loading wire:target="newFiles" class="mt-2 text-xs text-slate-500">Wird hochgeladen …</div>

                    @error('newFiles.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('pendingFiles') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('pendingFiles.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror

                    @if(count($pendingFiles) > 0)
                        <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">

                            <ul class="space-y-1">
                                @foreach($pendingFiles as $index => $pendingFile)
                                    <li wire:key="pending-file-{{ $index }}" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <span class="truncate">{{ $pendingFile->getClientOriginalName() }}</span>
                                        <span class="flex shrink-0 items-center gap-3">
                                            <span class="text-xs text-slate-400">{{ \Illuminate\Support\Number::fileSize($pendingFile->getSize(), precision: 1) }}</span>
                                            <button type="button" wire:click="removePendingFile({{ $index }})" class="text-xs font-medium text-red-600 hover:text-red-800">Entfernen</button>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Kategorie</label>
                                    <input
                                        type="text"
                                        list="member-file-categories"
                                        wire:model="uploadCategory"
                                        placeholder="z. B. Beitrittserklärung"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    >
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Notiz</label>
                                    <input type="text" wire:model="uploadNote" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="uploadVisible" class="h-4 w-4 rounded border-slate-300">
                                    Im Mitgliederbereich für das Mitglied sichtbar
                                </label>

                                <button
                                    type="button"
                                    wire:click="uploadFiles"
                                    wire:loading.attr="disabled"
                                    wire:target="uploadFiles"
                                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                >
                                    {{ count($pendingFiles) === 1 ? 'Datei speichern' : count($pendingFiles).' Dateien speichern' }}
                                </button>
                            </div>

                        </div>
                    @endif
                </div>

                <datalist id="member-file-categories">
                    @foreach($categories as $categoryOption)
                        <option value="{{ $categoryOption }}"></option>
                    @endforeach
                </datalist>
            @endif

            {{-- Inhalt --}}
            @if($items->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-200 px-6 py-10 text-center text-sm text-slate-500">
                    @if(trim($search) !== '')
                        Keine Treffer für „{{ $search }}“.
                    @elseif($folder === 'all')
                        Noch keine Dateien vorhanden.
                    @elseif($folder === 'documents')
                        Noch keine Dokumente hochgeladen.
                    @elseif($folder === 'fees')
                        Noch keine Beitragsschreiben versendet.
                    @else
                        Noch keine Rundschreiben erhalten.
                    @endif
                </div>
            @elseif($layout === 'grid')
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach($items as $item)
                        <div wire:key="{{ $item['key'] }}" class="group flex flex-col rounded-xl border border-slate-200 p-3 transition hover:border-slate-300 hover:shadow-sm">
                            <button
                                type="button"
                                @click="preview = @js(['url' => $item['url'], 'name' => $item['name'], 'kind' => $item['kind'], 'downloadUrl' => $item['downloadUrl']])"
                                class="flex h-24 items-center justify-center overflow-hidden rounded-lg bg-slate-50"
                            >
                                @if($item['kind'] === 'image')
                                    <img src="{{ $item['url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    @include('livewire.members.partials.file-icon', ['kind' => $item['kind'], 'size' => 'h-10 w-10'])
                                @endif
                            </button>

                            <div class="mt-2 min-w-0">
                                <div class="truncate text-sm font-medium text-slate-900" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
                                <div class="truncate text-xs text-slate-500">{{ $item['date']?->format('d.m.Y') }}{{ $item['meta'] ? ' · '.$item['meta'] : '' }}</div>
                                @if($folder === 'all')
                                    <div class="truncate text-xs text-slate-400">{{ $item['folderLabel'] }}</div>
                                @endif
                            </div>

                            @if($item['fileID'])
                                <div class="mt-2 flex items-center gap-3 text-xs">
                                    @if($item['visible'])
                                        <span class="text-emerald-700" title="Im Mitgliederbereich sichtbar">● sichtbar</span>
                                    @endif
                                    <button type="button" wire:click="editFile({{ $item['fileID'] }})" class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach($items as $item)
                        <div wire:key="{{ $item['key'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                            @include('livewire.members.partials.file-icon', ['kind' => $item['kind'], 'size' => 'h-8 w-8'])

                            <button
                                type="button"
                                @click="preview = @js(['url' => $item['url'], 'name' => $item['name'], 'kind' => $item['kind'], 'downloadUrl' => $item['downloadUrl']])"
                                class="min-w-0 flex-1 text-left"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-slate-900">{{ $item['name'] }}</span>
                                    @if($folder === 'all')
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $item['folderLabel'] }}</span>
                                    @endif
                                    @if($item['fileID'] && $item['visible'])
                                        <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">im Mitgliederbereich</span>
                                    @endif
                                </div>
                                <div class="truncate text-xs text-slate-500">
                                    {{ $item['date']?->format('d.m.Y') }}
                                    @if($item['meta']) · {{ $item['meta'] }} @endif
                                    @if($item['size']) · {{ $item['size'] }} @endif
                                    @if($item['note']) · {{ $item['note'] }} @endif
                                </div>
                            </button>

                            <div class="flex shrink-0 items-center gap-3 text-xs font-medium">
                                <a href="{{ $item['downloadUrl'] ?? $item['url'] }}" @if(! $item['downloadUrl']) target="_blank" @endif class="text-slate-600 hover:text-slate-900">
                                    {{ $item['downloadUrl'] ? 'Herunterladen' : 'Öffnen' }}
                                </a>

                                @if($item['fileID'])
                                    <button type="button" wire:click="editFile({{ $item['fileID'] }})" class="text-slate-600 hover:text-slate-900">Bearbeiten</button>
                                    <button
                                        type="button"
                                        wire:click="deleteFile({{ $item['fileID'] }})"
                                        wire:confirm="Datei „{{ $item['name'] }}“ endgültig löschen?"
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        Löschen
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

    </div>

    {{-- Bearbeiten --}}
    @if($editingFileID)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:keydown.escape.window="cancelEdit">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h3 class="font-semibold text-slate-900">Datei bearbeiten</h3>
                </div>

                <div class="space-y-4 px-6 py-5">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Name</label>
                        <input type="text" wire:model="editName" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('editName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Kategorie</label>
                        <input type="text" list="member-file-categories" wire:model="editCategory" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('editCategory') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Notiz</label>
                        <textarea wire:model="editNote" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
                        @error('editNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="editVisible" class="h-4 w-4 rounded border-slate-300">
                        Im Mitgliederbereich für das Mitglied sichtbar
                    </label>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                    <button
                        type="button"
                        wire:click="deleteFile({{ $editingFileID }})"
                        wire:confirm="Datei „{{ $editName }}“ endgültig löschen?"
                        class="text-sm font-medium text-red-600 hover:text-red-800"
                    >
                        Löschen
                    </button>

                    <div class="flex gap-3">
                        <button type="button" wire:click="cancelEdit" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Abbrechen</button>
                        <button type="button" wire:click="saveFile" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Vorschau --}}
    <template x-teleport="body">
        <div
            x-show="preview"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex flex-col bg-slate-900/80 p-3 sm:p-6"
            @click.self="preview = null"
        >
            <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div class="truncate text-sm font-semibold text-slate-900" x-text="preview?.name"></div>
                    <div class="flex shrink-0 items-center gap-3 text-sm font-medium">
                        <a :href="preview?.downloadUrl ?? preview?.url" x-show="preview?.downloadUrl" class="text-slate-600 hover:text-slate-900">Herunterladen</a>
                        <a :href="preview?.url" target="_blank" class="text-slate-600 hover:text-slate-900">In neuem Tab</a>
                        <button type="button" @click="preview = null" class="rounded-lg px-2 py-1 text-slate-500 hover:bg-slate-100" aria-label="Schließen">✕</button>
                    </div>
                </div>

                <div class="flex flex-1 items-center justify-center overflow-auto bg-slate-100">
                    <template x-if="preview?.kind === 'image'">
                        <img :src="preview.url" :alt="preview.name" class="max-h-full max-w-full object-contain">
                    </template>
                    <template x-if="preview?.kind === 'pdf'">
                        <iframe :src="preview.url" class="h-full min-h-[70vh] w-full" title="Vorschau"></iframe>
                    </template>
                    <template x-if="preview && preview.kind !== 'image' && preview.kind !== 'pdf'">
                        <div class="p-10 text-center text-sm text-slate-600">
                            Für diesen Dateityp gibt es keine Vorschau.
                            <div class="mt-4">
                                <a :href="preview.downloadUrl ?? preview.url" class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Herunterladen</a>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

</div>

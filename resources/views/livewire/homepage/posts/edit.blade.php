<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a
                href="{{ route('homepage-admin.posts.index') }}"
                wire:navigate
                class="text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                ← Alle Beiträge
            </a>

            <h2 class="mt-1 text-2xl font-bold tracking-tight">
                {{ $post ? 'Beitrag bearbeiten' : 'Neuer Beitrag' }}
            </h2>
        </div>

        @if($post)
            <a
                href="{{ route('homepage-admin.posts.preview', $post) }}"
                target="_blank"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Vorschau ↗
            </a>
        @endif
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-3">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">

            <div>
                <label class="mb-1 block text-sm font-medium">Titel</label>

                <input
                    type="text"
                    wire:model.live.debounce.500ms="title"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >

                @error('title')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Adresse</label>

                <div class="flex items-center rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-slate-300">
                    <span class="pl-3 text-sm text-slate-400">/news/</span>

                    <input
                        type="text"
                        wire:model.blur="slug"
                        class="w-full rounded-lg border-0 px-1 py-2 focus:ring-0"
                    >
                </div>

                @error('slug')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">
                    Kurztext
                    <span class="font-normal text-slate-400">(optional, für Übersicht und Suchmaschinen)</span>
                </label>

                <textarea
                    wire:model="excerpt"
                    rows="2"
                    maxlength="500"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                ></textarea>

                @error('excerpt')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Text</label>

                <div
                    wire:ignore
                    x-data="{ quill: null }"
                    x-init="
                        quill = new Quill($refs.editor, {
                            theme: 'snow',
                            modules: {
                                toolbar: [
                                    [{ header: [2, 3, false] }],
                                    ['bold', 'italic', 'underline'],
                                    ['link', 'blockquote'],
                                    [{ list: 'ordered' }, { list: 'bullet' }],
                                    ['clean']
                                ]
                            }
                        });

                        quill.root.innerHTML = @js($bodyHtml) || '';

                        quill.on('text-change', () => {
                            $wire.set('bodyHtml', quill.root.innerHTML, false);
                        });
                    "
                >
                    <div x-ref="editor" class="min-h-[320px] bg-white"></div>
                </div>

                @error('bodyHtml')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </section>

        <div class="space-y-6">

            <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <h3 class="font-semibold">Veröffentlichung</h3>

                <div>
                    <label class="mb-1 block text-sm font-medium">Status</label>

                    <select wire:model.live="status" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Veröffentlichen am</label>

                    <input
                        type="datetime-local"
                        wire:model="publishedAt"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >

                    <p class="mt-1 text-xs text-slate-500">
                        Leer = sofort beim Veröffentlichen. Ein Datum in der Zukunft plant den Beitrag ein.
                    </p>

                    @error('publishedAt')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save,newImages"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="save">
                        {{ $post ? 'Änderungen speichern' : 'Beitrag anlegen' }}
                    </span>

                    <span wire:loading wire:target="save">Speichern …</span>
                </button>

            </section>

            <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <div>
                    <h3 class="font-semibold">Bildergalerie</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Das erste Bild ist das Titelbild. Bilder werden automatisch verkleinert, Metadaten (z. B. GPS) entfernt.
                    </p>
                </div>

                <label class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 hover:border-slate-400">
                    <span class="font-medium text-slate-700">Bilder auswählen</span>
                    <span class="text-xs">JPG, PNG oder WebP, je max. 10 MB</span>

                    <input type="file" wire:model="newImages" multiple accept="image/jpeg,image/png,image/webp" class="hidden">
                </label>

                <div wire:loading wire:target="newImages" class="text-sm text-slate-500">
                    Bilder werden hochgeladen …
                </div>

                @error('newImages')
                <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                @error('newImages.*')
                <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                @if(! $post && $newImages !== [])
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($newImages as $index => $pendingImage)
                            <div wire:key="pending-{{ $index }}" class="relative">
                                <img src="{{ $pendingImage->temporaryUrl() }}" alt="" class="aspect-[4/3] w-full rounded-lg object-cover">

                                <button
                                    type="button"
                                    wire:click="removePendingImage({{ $index }})"
                                    class="absolute right-1 top-1 rounded-full bg-white/90 px-2 text-xs font-bold text-red-600"
                                    title="Entfernen"
                                >×</button>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-slate-500">Die Bilder werden beim Anlegen des Beitrags gespeichert.</p>
                @endif

                @if($images->isNotEmpty())
                    <ul class="space-y-3">
                        @foreach($images as $image)
                            <li wire:key="image-{{ $image->postImageID }}" class="flex gap-3">
                                <img
                                    src="{{ $imageUrls->postImage($image) }}"
                                    alt=""
                                    class="h-16 w-20 flex-none rounded-lg object-cover"
                                >

                                <div class="min-w-0 flex-1 space-y-1">
                                    <input
                                        type="text"
                                        wire:model="captions.{{ $image->postImageID }}"
                                        placeholder="Bildunterschrift"
                                        maxlength="255"
                                        class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm"
                                    >

                                    <div class="flex items-center gap-3 text-xs">
                                        @if($loop->first)
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Titelbild</span>
                                        @else
                                            <button type="button" wire:click="moveImage({{ $image->postImageID }}, 'up')" class="text-slate-500 hover:text-slate-800">↑ nach vorne</button>
                                        @endif

                                        @unless($loop->last)
                                            <button type="button" wire:click="moveImage({{ $image->postImageID }}, 'down')" class="text-slate-500 hover:text-slate-800">↓</button>
                                        @endunless

                                        <button
                                            type="button"
                                            wire:click="deleteImage({{ $image->postImageID }})"
                                            wire:confirm="Bild wirklich löschen?"
                                            class="ml-auto text-red-600 hover:text-red-800"
                                        >Löschen</button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <p class="text-xs text-slate-500">Bildunterschriften werden mit „Änderungen speichern“ übernommen.</p>
                @endif

            </section>

            <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <div>
                    <h3 class="font-semibold">Downloads</h3>
                    <p class="mt-1 text-xs text-slate-500">PDFs zum Beitrag, z. B. Ergebnisliste oder Einladung (je max. 20 MB).</p>
                </div>

                <label class="inline-block cursor-pointer rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    PDF auswählen
                    <input type="file" wire:model="newAttachments" multiple accept="application/pdf" class="hidden">
                </label>

                <span wire:loading wire:target="newAttachments" class="text-sm text-slate-500">Wird hochgeladen …</span>

                @error('newAttachments.*')
                <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                @if(! $post && $newAttachments !== [])
                    <ul class="space-y-1 text-sm">
                        @foreach($newAttachments as $index => $pendingAttachment)
                            <li wire:key="pending-attachment-{{ $index }}" class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ $pendingAttachment->getClientOriginalName() }}</span>
                                <button type="button" wire:click="removePendingAttachment({{ $index }})" class="text-red-600">×</button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($attachments->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach($attachments as $attachment)
                            <li wire:key="attachment-{{ $attachment->postAttachmentID }}" class="flex items-center gap-2">
                                <span class="rounded bg-red-600 px-1.5 text-[10px] font-bold text-white">PDF</span>
                                <input
                                    type="text"
                                    wire:model="attachmentLabels.{{ $attachment->postAttachmentID }}"
                                    maxlength="200"
                                    class="min-w-0 flex-1 rounded-md border border-slate-300 px-2 py-1 text-sm"
                                >
                                <a href="{{ $imageUrls->attachment($attachment) }}" target="_blank" class="text-xs text-slate-500 hover:text-slate-800">öffnen</a>
                                <button
                                    type="button"
                                    wire:click="deleteAttachment({{ $attachment->postAttachmentID }})"
                                    wire:confirm="Download wirklich löschen?"
                                    class="text-xs text-red-600 hover:text-red-800"
                                >Löschen</button>
                            </li>
                        @endforeach
                    </ul>

                    <p class="text-xs text-slate-500">Bezeichnungen werden mit „Änderungen speichern“ übernommen.</p>
                @endif

            </section>

        </div>

    </div>

</div>

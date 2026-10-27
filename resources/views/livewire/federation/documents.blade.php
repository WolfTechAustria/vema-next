<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.federation-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Dokumente
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Formulare, Ausschreibungen oder Satzungen für eure Mitgliedsvereine. Sie stehen dort unter „Verband“ zum Download bereit.
        </p>
    </div>

    @if($isAdmin)
        <form wire:submit="upload" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Titel</label>
                <input type="text" wire:model="title" placeholder="z. B. Meldeformular Landesmeisterschaft" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Datei</label>
                <x-upload-guard :max-kb="10240">
                    <input type="file" wire:model="file" class="block w-full text-sm">
                </x-upload-guard>
                <x-upload-error field="file" :max-kb="10240" />
            </div>

            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                Freigeben
            </button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Dokument</th>
                    <th class="px-4 py-3">Hochgeladen</th>
                    @if($isAdmin)
                        <th class="px-4 py-3"></th>
                    @endif
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($documents as $document)
                    <tr wire:key="document-{{ $document->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('federation-files.show', $document) }}" target="_blank" class="font-medium text-slate-900 hover:underline">{{ $document->title }}</a>
                            <div class="text-xs text-slate-500">{{ $document->file_name }} · {{ \App\Enums\Limit::formatMegabytes($document->file_size / 1024 / 1024) }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $document->created_at->format('d.m.Y') }}</td>
                        @if($isAdmin)
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="delete({{ $document->id }})" wire:confirm="Dokument löschen? Es verschwindet auch bei allen Vereinen." class="text-sm text-red-600 underline hover:text-red-800">
                                    Löschen
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-8 text-center text-slate-500">Noch keine Dokumente freigegeben.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

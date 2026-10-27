<div class="space-y-6">

    @include('partials.flash-messages')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Verband
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Mitteilungen und Dokumente der Verbände, denen euer Verein angehört.
            @if(auth()->user()?->hasRole('admin'))
                Verwalten unter <a href="{{ route('admin.settings.federations') }}" wire:navigate class="underline">Einstellungen → Verbände</a>.
            @endif
        </p>
    </div>

    @unless($hasFederations)
        <p class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-sm text-slate-500 shadow-sm">
            Euer Verein ist mit keinem Verband verbunden.
        </p>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            <section class="space-y-3 lg:col-span-2">
                <h3 class="font-semibold text-slate-900">Mitteilungen</h3>

                @forelse($messages as $message)
                    @php($isUnread = ! in_array($message->id, $readIds, true))

                    <article wire:key="message-{{ $message->id }}" class="rounded-xl border bg-white shadow-sm {{ $isUnread ? 'border-sky-300' : 'border-slate-200' }}">
                        <button type="button" wire:click="open({{ $message->id }})" class="flex w-full items-start justify-between gap-3 px-5 py-4 text-left">
                            <div>
                                <div class="font-semibold text-slate-900">
                                    {{ $message->subject }}
                                    @if($isUnread)
                                        <span class="ml-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800">neu</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500">{{ $message->federation->name }} · {{ $message->created_at->format('d.m.Y') }}</div>
                            </div>
                            <span class="text-slate-400">{{ $openMessageId === $message->id ? '▴' : '▾' }}</span>
                        </button>

                        @if($openMessageId === $message->id)
                            <div class="border-t border-slate-100 px-5 py-4 text-sm text-slate-700">
                                {!! nl2br(e($message->body)) !!}

                                @if($message->attachments->isNotEmpty())
                                    <ul class="mt-3 flex flex-wrap gap-2">
                                        @foreach($message->attachments as $attachment)
                                            <li><a href="{{ route('federation-files.show', $attachment) }}" target="_blank" class="underline">{{ $attachment->file_name }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-500">Noch keine Mitteilungen.</p>
                @endforelse
            </section>

            <div class="space-y-6">
                <section class="space-y-3">
                    <h3 class="font-semibold text-slate-900">Nächste Termine</h3>

                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white px-4 shadow-sm">
                        @forelse($events as $event)
                            <li wire:key="event-{{ $event->id }}" class="py-2 text-sm">
                                <div class="font-medium text-slate-900">{{ $event->title }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $event->starts_at->format($event->all_day ? 'd.m.Y' : 'd.m.Y H:i') }}
                                    @if($event->location) · {{ $event->location }} @endif
                                    · {{ $event->federation->name }}
                                </div>
                            </li>
                        @empty
                            <li class="py-2 text-sm text-slate-500">Keine anstehenden Termine.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="space-y-3">
                    <h3 class="font-semibold text-slate-900">Dokumente</h3>

                    @forelse($documents as $federationName => $files)
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $federationName }}</div>
                            <ul class="space-y-1 text-sm">
                                @foreach($files as $file)
                                    <li wire:key="document-{{ $file->id }}">
                                        <a href="{{ route('federation-files.show', $file) }}" target="_blank" class="text-slate-800 underline">{{ $file->title }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Noch keine Dokumente.</p>
                    @endforelse
                </section>
            </div>

        </div>
    @endunless

</div>

<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">Beiträge</h2>

            <p class="mt-1 text-sm text-slate-500">
                News für die Vereinshomepage. Veröffentlichte Beiträge erscheinen auf der Startseite und unter „News“.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('homepage.home') }}"
                target="_blank"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Homepage ansehen ↗
            </a>

            <a
                href="{{ route('homepage-admin.posts.create') }}"
                wire:navigate
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
            >
                Neuer Beitrag
            </a>
        </div>
    </div>

    @include('partials.flash-messages')

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-6 py-3">
            @foreach(['' => 'Alle', 'published' => 'Veröffentlicht', 'draft' => 'Entwürfe'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $value }}')"
                    class="rounded-full px-3 py-1 text-sm font-medium
                    {{ $filter === $value ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="divide-y divide-slate-100">

            @forelse($posts as $post)
                <div wire:key="post-{{ $post->postID }}" class="flex flex-wrap items-center gap-4 px-6 py-4 sm:flex-nowrap">

                    @if($post->coverImage)
                        <img src="{{ $imageUrls->postImage($post->coverImage) }}" alt="" class="h-14 w-20 flex-none rounded-lg object-cover">
                    @else
                        <div class="flex h-14 w-20 flex-none items-center justify-center rounded-lg bg-slate-100 text-slate-400">✎</div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <a
                            href="{{ route('homepage-admin.posts.edit', $post) }}"
                            wire:navigate
                            class="font-medium text-slate-900 hover:underline"
                        >
                            {{ $post->title }}
                        </a>

                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            @if($post->isPublic())
                                <span class="rounded-full bg-green-50 px-2 py-0.5 font-medium text-green-700">Veröffentlicht</span>
                                <span>{{ $post->published_at->format('d.m.Y H:i') }}</span>
                            @elseif($post->status === \App\Enums\PostStatus::Published)
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-700">Geplant</span>
                                <span>ab {{ $post->published_at->format('d.m.Y H:i') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Entwurf</span>
                            @endif

                            <span>{{ $post->images_count }} Bild(er)</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('homepage-admin.posts.preview', $post) }}" target="_blank" class="font-medium text-slate-600 hover:text-slate-900">Vorschau</a>

                        <a href="{{ route('homepage-admin.posts.edit', $post) }}" wire:navigate class="font-medium text-slate-600 hover:text-slate-900">Bearbeiten</a>

                        <button
                            type="button"
                            wire:click="deletePost({{ $post->postID }})"
                            wire:confirm="Beitrag samt Bildern wirklich löschen?"
                            class="font-medium text-red-600 hover:text-red-800"
                        >
                            Löschen
                        </button>
                    </div>

                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    Noch keine Beiträge vorhanden.
                </div>
            @endforelse

        </div>

        @if($posts->hasPages())
            <div class="border-t border-slate-200 px-6 py-3">
                {{ $posts->links() }}
            </div>
        @endif

    </section>

</div>

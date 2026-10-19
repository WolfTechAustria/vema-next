@if($data['draftCount'] > 0)
    <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
        {{ $data['draftCount'] }} {{ $data['draftCount'] === 1 ? 'Entwurf' : 'Entwürfe' }}
    </div>

    <div class="mb-4 space-y-1">
        @foreach($data['drafts'] as $post)
            <a wire:key="dashboard-draft-{{ $post->postID }}" href="{{ route('homepage-admin.posts.edit', $post) }}" wire:navigate class="block truncate text-sm font-medium text-slate-900 hover:underline">
                {{ $post->title }}
            </a>
        @endforeach
    </div>
@endif

<div class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Zuletzt veröffentlicht</div>

<div class="space-y-1">
    @forelse($data['published'] as $post)
        <div wire:key="dashboard-post-{{ $post->postID }}" class="flex items-center justify-between gap-3 text-sm">
            <a href="{{ route('homepage-admin.posts.edit', $post) }}" wire:navigate class="min-w-0 truncate text-slate-900 hover:underline">{{ $post->title }}</a>
            <span class="shrink-0 text-slate-500">{{ $post->published_at->format('d.m.Y') }}</span>
        </div>
    @empty
        <p class="text-sm text-slate-500">Noch keine Beiträge veröffentlicht.</p>
    @endforelse
</div>

<a href="{{ route('homepage-admin.posts.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-slate-700 hover:text-slate-900">
    Alle Beiträge →
</a>

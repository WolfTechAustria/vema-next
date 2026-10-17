<?php

namespace App\Livewire\Homepage\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\ActivityLogger;
use App\Services\HomepageImageUrls;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Übersicht der Homepage-Beiträge.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = '';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function deletePost(int $postID): void
    {
        $post = Post::findOrFail($postID);

        $post->deleteWithImages();

        ActivityLogger::log(
            'homepage.post_deleted',
            'Homepage-Beitrag „'.$post->title.'“ wurde gelöscht.',
            'Post',
            $postID
        );

        session()->flash('success', 'Beitrag wurde gelöscht.');
    }

    public function render()
    {
        $posts = Post::query()
            ->with('coverImage')
            ->withCount('images')
            ->when($this->filter === 'draft', fn ($query) => $query->where('status', PostStatus::Draft))
            ->when($this->filter === 'published', fn ($query) => $query->where('status', PostStatus::Published))
            ->orderByRaw('published_at IS NULL DESC')
            ->orderByDesc('published_at')
            ->orderByDesc('postID')
            ->paginate(20);

        return view('livewire.homepage.posts.index', [
            'posts' => $posts,
            'imageUrls' => app(HomepageImageUrls::class),
        ])->layout('layouts.app', [
            'title' => 'Homepage-Beiträge | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

<?php

namespace App\Livewire\Homepage\Posts;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostImage;
use App\Services\ActivityLogger;
use App\Services\HomepageFileService;
use App\Services\HomepageImageService;
use App\Services\HomepageImageUrls;
use App\Services\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Beitrag der Homepage anlegen bzw. bearbeiten, inkl. Bildergalerie.
 */
class Edit extends Component
{
    use WithFileUploads;

    public ?Post $post = null;

    public string $title = '';

    public string $slug = '';

    public bool $slugTouched = false;

    public string $excerpt = '';

    public string $bodyHtml = '';

    public string $status = 'draft';

    public string $publishedAt = '';

    /**
     * Neu ausgewählte Bilder; bei neuen Beiträgen bis zum Speichern vorgehalten.
     *
     * @var array<int, UploadedFile>
     */
    public array $newImages = [];

    /**
     * Neues Titelbild, das vorne in die Galerie eingereiht wird.
     */
    public ?UploadedFile $newCover = null;

    /**
     * Neu ausgewählte PDFs; bei neuen Beiträgen bis zum Speichern vorgehalten.
     *
     * @var array<int, UploadedFile>
     */
    public array $newAttachments = [];

    /**
     * Bezeichnungen der vorhandenen Downloads, nach postAttachmentID.
     *
     * @var array<int, string>
     */
    public array $attachmentLabels = [];

    /**
     * Bildunterschriften der vorhandenen Bilder, nach postImageID.
     *
     * @var array<int, string>
     */
    public array $captions = [];

    public function mount(?Post $post = null): void
    {
        if ($post === null || ! $post->exists) {
            $this->post = null;

            return;
        }

        $this->post = $post;
        $this->title = $post->title;
        $this->slug = $post->slug;
        $this->slugTouched = true;
        $this->excerpt = $post->excerpt ?? '';
        $this->bodyHtml = $post->body_html ?? '';
        $this->status = $post->status->value;
        $this->publishedAt = $post->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->attachmentLabels = $post->attachments->pluck('label', 'postAttachmentID')->all();
        $this->captions = $post->images->pluck('caption', 'postImageID')->map(fn (?string $caption) => $caption ?? '')->all();
    }

    public function updatedTitle(): void
    {
        if (! $this->slugTouched) {
            $this->slug = Post::uniqueSlug($this->title, $this->post?->postID);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugTouched = true;
    }

    public function updatedNewImages(HomepageImageService $images): void
    {
        $this->validate($this->imageRules());

        if ($this->post !== null) {
            $this->storeNewImages($images);
        }
    }

    public function updatedNewAttachments(HomepageFileService $files): void
    {
        $this->validate($this->attachmentRules());

        if ($this->post !== null) {
            $this->storeNewAttachments($files);
        }
    }

    public function removePendingAttachment(int $index): void
    {
        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }

    public function deleteAttachment(int $postAttachmentID, HomepageFileService $files): void
    {
        abort_if($this->post === null, 404);

        $attachment = $this->post->attachments()->whereKey($postAttachmentID)->first();

        abort_if($attachment === null, 404);

        $files->delete($attachment->file_path);
        $attachment->delete();
        unset($this->attachmentLabels[$postAttachmentID]);

        ActivityLogger::log(
            'homepage.post_attachment_deleted',
            'Download „'.$attachment->label.'“ aus Homepage-Beitrag „'.$this->post->title.'“ wurde gelöscht.',
            'Post',
            $this->post->postID
        );
    }

    public function removePendingImage(int $index): void
    {
        unset($this->newImages[$index]);
        $this->newImages = array_values($this->newImages);
    }

    public function save(HomepageImageService $images, HtmlSanitizer $sanitizer, HomepageFileService $files): void
    {
        $this->slug = Str::slug($this->slug, '-', 'de');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('tb_posts', 'slug')->ignore($this->post?->postID, 'postID'),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'bodyHtml' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'publishedAt' => ['nullable', 'date'],
            ...$this->imageRules(),
            ...$this->attachmentRules(),
        ], [], [
            'title' => 'Titel',
            'slug' => 'Adresse',
            'excerpt' => 'Kurztext',
            'publishedAt' => 'Veröffentlichungsdatum',
        ]);

        $status = PostStatus::from($validated['status']);
        $publishedAt = filled($validated['publishedAt']) ? Carbon::parse($validated['publishedAt']) : null;

        if ($status === PostStatus::Published && $publishedAt === null) {
            $publishedAt = now();
        }

        $attributes = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => filled($validated['excerpt']) ? $validated['excerpt'] : null,
            'body_html' => $sanitizer->sanitize($validated['bodyHtml']) ?: null,
            'status' => $status,
            'published_at' => $publishedAt,
        ];

        if ($this->post === null) {
            $post = Post::create([...$attributes, 'created_by' => auth()->id()]);

            ActivityLogger::log(
                'homepage.post_created',
                'Homepage-Beitrag „'.$post->title.'“ wurde angelegt.',
                'Post',
                $post->postID,
                ActivityLogger::created($post->fresh()->activitySnapshot(), Post::ACTIVITY_LABELS)
            );

            $this->post = $post;
            $this->storeNewImages($images);
            $this->storeNewAttachments($files);

            session()->flash('success', 'Beitrag wurde angelegt.');

            $this->redirectRoute('homepage-admin.posts.edit', ['post' => $post], navigate: true);

            return;
        }

        $before = $this->post->activitySnapshot();
        $wasPublic = $this->post->isPublic();

        $this->post->update($attributes);
        $this->saveCaptions();
        $this->saveAttachmentLabels();

        $changes = ActivityLogger::changes($before, $this->post->fresh()->activitySnapshot(), Post::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                ! $wasPublic && $this->post->isPublic() ? 'homepage.post_published' : 'homepage.post_updated',
                'Homepage-Beitrag „'.$this->post->title.'“ wurde geändert.',
                'Post',
                $this->post->postID,
                $changes
            );
        }

        session()->flash('success', 'Beitrag wurde gespeichert.');
    }

    public function moveImage(int $postImageID, string $direction): void
    {
        $images = $this->ownedImages();
        $index = $images->search(fn (PostImage $image) => $image->postImageID === $postImageID);

        abort_if($index === false, 404);

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! $images->has($target)) {
            return;
        }

        $ordered = $images->values()->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        $this->reorder(collect($ordered));
    }

    /**
     * Macht ein vorhandenes Bild zum Titelbild (erste Stelle der Galerie).
     */
    public function makeCover(int $postImageID): void
    {
        $images = $this->ownedImages();
        $cover = $images->firstWhere('postImageID', $postImageID);

        abort_if($cover === null, 404);

        $this->reorder($images->reject(fn (PostImage $image) => $image->is($cover))->prepend($cover));

        ActivityLogger::log(
            'homepage.post_cover_changed',
            'Titelbild von Homepage-Beitrag „'.$this->post->title.'“ wurde geändert.',
            'Post',
            $this->post->postID
        );

        session()->flash('success', 'Titelbild wurde geändert.');
    }

    /**
     * Lädt ein neues Titelbild hoch; das bisherige bleibt in der Galerie.
     */
    public function updatedNewCover(HomepageImageService $images): void
    {
        abort_if($this->post === null, 404);

        $this->validate([
            'newCover' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
        ], [], ['newCover' => 'Titelbild']);

        try {
            $cover = $images->storePostImage($this->post, $this->newCover);
        } catch (RuntimeException $exception) {
            $this->addError('newCover', $exception->getMessage());

            return;
        } finally {
            $this->newCover = null;
        }

        $this->captions[$cover->postImageID] = '';
        $this->makeCover($cover->postImageID);
    }

    /**
     * @param  Collection<int, PostImage>  $ordered
     */
    private function reorder(Collection $ordered): void
    {
        foreach ($ordered->values() as $position => $image) {
            if ($image->sort !== $position + 1) {
                $image->update(['sort' => $position + 1]);
            }
        }
    }

    public function deleteImage(int $postImageID, HomepageImageService $images): void
    {
        $image = $this->ownedImages()->firstWhere('postImageID', $postImageID);

        abort_if($image === null, 404);

        $images->deletePostImage($image);
        unset($this->captions[$postImageID]);

        ActivityLogger::log(
            'homepage.post_image_deleted',
            'Bild aus Homepage-Beitrag „'.$this->post->title.'“ wurde gelöscht.',
            'Post',
            $this->post->postID
        );
    }

    private function storeNewImages(HomepageImageService $images): void
    {
        if ($this->newImages === [] || $this->post === null) {
            return;
        }

        $stored = 0;

        foreach ($this->newImages as $upload) {
            try {
                $image = $images->storePostImage($this->post, $upload);
                $this->captions[$image->postImageID] = '';
                $stored++;
            } catch (RuntimeException $exception) {
                session()->flash('error', $exception->getMessage());
            }
        }

        $this->newImages = [];

        if ($stored > 0) {
            ActivityLogger::log(
                'homepage.post_images_added',
                $stored.' Bild(er) zu Homepage-Beitrag „'.$this->post->title.'“ hinzugefügt.',
                'Post',
                $this->post->postID
            );
        }
    }

    private function storeNewAttachments(HomepageFileService $files): void
    {
        if ($this->newAttachments === [] || $this->post === null) {
            return;
        }

        $stored = 0;

        foreach ($this->newAttachments as $upload) {
            try {
                $path = $files->storePdf($upload, 'homepage/posts/'.$this->post->postID);
            } catch (RuntimeException $exception) {
                session()->flash('error', $exception->getMessage());

                continue;
            }

            $attachment = $this->post->attachments()->create([
                'label' => Str::limit(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME), 200, '') ?: 'Download',
                'file_path' => $path,
                'file_size' => $upload->getSize(),
                'sort' => (int) $this->post->attachments()->max('sort') + 1,
            ]);

            $this->attachmentLabels[$attachment->postAttachmentID] = $attachment->label;
            $stored++;
        }

        $this->newAttachments = [];

        if ($stored > 0) {
            ActivityLogger::log(
                'homepage.post_attachments_added',
                $stored.' Download(s) zu Homepage-Beitrag „'.$this->post->title.'“ hinzugefügt.',
                'Post',
                $this->post->postID
            );
        }
    }

    private function saveAttachmentLabels(): void
    {
        foreach ($this->post->attachments()->get() as $attachment) {
            $label = mb_substr(trim((string) ($this->attachmentLabels[$attachment->postAttachmentID] ?? '')), 0, 200);

            if ($label !== '' && $label !== $attachment->label) {
                $attachment->update(['label' => $label]);
            }
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function attachmentRules(): array
    {
        return [
            'newAttachments' => ['array', 'max:10'],
            'newAttachments.*' => ['file', 'mimes:pdf', 'max:'.HomepageFileService::MAX_SIZE_KB],
        ];
    }

    private function saveCaptions(): void
    {
        foreach ($this->ownedImages() as $image) {
            $caption = trim((string) ($this->captions[$image->postImageID] ?? ''));
            $caption = mb_substr($caption, 0, 255);

            if ($caption !== (string) $image->caption) {
                $image->update(['caption' => $caption !== '' ? $caption : null]);
            }
        }
    }

    /**
     * @return Collection<int, PostImage>
     */
    private function ownedImages(): Collection
    {
        abort_if($this->post === null, 404);

        return $this->post->images()->get()->values();
    }

    /**
     * @return array<string, list<string>>
     */
    private function imageRules(): array
    {
        return [
            'newImages' => ['array', 'max:30'],
            'newImages.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
        ];
    }

    public function render()
    {
        $images = $this->post?->images()->get() ?? collect();

        return view('livewire.homepage.posts.edit', [
            'images' => $images,
            'attachments' => $this->post?->attachments()->get() ?? collect(),
            'statuses' => PostStatus::cases(),
            'imageUrls' => app(HomepageImageUrls::class),
        ])->layout('layouts.app', [
            'title' => ($this->post ? 'Beitrag bearbeiten' : 'Neuer Beitrag').' | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

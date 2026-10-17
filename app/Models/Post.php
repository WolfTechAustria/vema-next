<?php

namespace App\Models;

use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * News-Beitrag der Vereinshomepage mit optionaler Bildergalerie.
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'title' => 'Titel',
        'slug' => 'Adresse',
        'excerpt' => 'Kurztext',
        'status' => 'Status',
        'published_at' => 'Veröffentlicht am',
    ];

    protected $table = 'tb_posts';

    protected $primaryKey = 'postID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PostImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class, 'postID', 'postID')->orderBy('sort')->orderBy('postImageID');
    }

    /**
     * @return HasMany<PostAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class, 'postID', 'postID')->orderBy('sort')->orderBy('postAttachmentID');
    }

    /**
     * Titelbild = erstes Bild der Galerie.
     *
     * @return HasOne<PostImage, $this>
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(PostImage::class, 'postID', 'postID')->orderBy('sort')->orderBy('postImageID');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Öffentlich sichtbar: veröffentlicht und Veröffentlichungsdatum erreicht.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * Eindeutige Adresse aus dem Titel, bei Bedarf mit Zähler (-2, -3 …).
     */
    public static function uniqueSlug(string $title, ?int $ignorePostID = null): string
    {
        $base = Str::slug($title, '-', 'de') ?: 'beitrag';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignorePostID, fn (Builder $query) => $query->where('postID', '!=', $ignorePostID))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Kurztext für Übersichten: eigener Kurztext oder Anfang des Beitrags.
     */
    public function teaser(int $length = 220): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        $text = html_entity_decode(strip_tags((string) preg_replace('#</(p|li|h[1-6]|blockquote)>|<br\s*/?>#i', ' ', (string) $this->body_html)));

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), $length);
    }

    public function deleteWithImages(): void
    {
        foreach ($this->images as $image) {
            Storage::disk('local')->delete([$image->file_path, $image->thumb_path]);
        }

        foreach ($this->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $this->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'status' => $this->status?->label(),
            'published_at' => $this->published_at?->format('d.m.Y H:i'),
        ];
    }
}

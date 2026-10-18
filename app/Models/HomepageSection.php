<?php

namespace App\Models;

use App\Enums\HomepageSectionType;
use Database\Factories\HomepageSectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Abschnitt einer Homepage-Seite (Baukasten). Typabhängige Inhalte wie
 * Kacheln, Button oder Bildunterschriften liegen in „data“.
 */
class HomepageSection extends Model
{
    /** @use HasFactory<HomepageSectionFactory> */
    use HasFactory;

    public const AREA_PAGE = 'page';

    public const AREA_ABOUT = 'about';

    protected $table = 'tb_homepage_sections';

    protected $primaryKey = 'sectionID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HomepageSectionType::class,
            'sort' => 'integer',
            'alt_background' => 'boolean',
            'data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<HomepagePage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(HomepagePage::class, 'pageID', 'pageID');
    }

    /**
     * @param  Builder<HomepageSection>  $query
     * @return Builder<HomepageSection>
     */
    public function scopeForAbout(Builder $query): Builder
    {
        return $query->where('area', self::AREA_ABOUT)->whereNull('pageID');
    }

    /**
     * @param  Builder<HomepageSection>  $query
     * @return Builder<HomepageSection>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('sectionID');
    }

    /**
     * @return BelongsTo<HomepageSection, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parentID', 'sectionID');
    }

    /**
     * Abschnitte in den Spalten eines Spalten-Abschnitts.
     *
     * @return HasMany<HomepageSection, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parentID', 'sectionID')->orderBy('sort')->orderBy('sectionID');
    }

    /**
     * @param  Builder<HomepageSection>  $query
     * @return Builder<HomepageSection>
     */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parentID');
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Darf der Abschnitt öffentlich ausgeliefert werden (Bilder, Datei)?
     */
    public function isPublic(): bool
    {
        if ($this->parentID !== null && $this->parent) {
            return $this->parent->isPublic();
        }

        if ($this->area === self::AREA_ABOUT) {
            return true;
        }

        return (bool) $this->page?->published;
    }

    public function deleteWithFiles(): void
    {
        foreach ($this->children()->get() as $child) {
            $child->deleteWithFiles();
        }

        Storage::disk('local')->delete(array_filter([$this->image_path, $this->image2_path, $this->file_path]));

        $this->delete();
    }
}

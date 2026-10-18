<?php

namespace App\Models;

use Database\Factories\HomepagePageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Frei anlegbare Seite der Vereinshomepage (z. B. Impressum, Geschichte).
 */
class HomepagePage extends Model
{
    /** @use HasFactory<HomepagePageFactory> */
    use HasFactory;

    /**
     * Adressen, die von festen Seiten der Homepage belegt sind.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = ['news', 'ueber-uns', 'vorstand', 'kontakt', 'bilder', 'titelbild', 'vorstandsfoto', 'logo', 'ankuendigung', 'downloads', 'abschnitt', 'termine', 'hinweis'];

    /**
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'title' => 'Titel',
        'subtitle' => 'Überschrift im Seitenkopf',
        'slug' => 'Adresse',
        'sort' => 'Reihenfolge',
        'in_menu' => 'Im Menü',
        'published' => 'Veröffentlicht',
    ];

    protected $table = 'tb_homepage_pages';

    protected $primaryKey = 'pageID';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'in_menu' => 'boolean',
        'published' => 'boolean',
    ];

    /**
     * @return HasMany<HomepageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(HomepageSection::class, 'pageID', 'pageID')->whereNull('parentID')->orderBy('sort')->orderBy('sectionID');
    }

    public function deleteWithSections(): void
    {
        foreach ($this->sections as $section) {
            $section->deleteWithFiles();
        }

        $this->delete();
    }

    /**
     * @param  Builder<HomepagePage>  $query
     * @return Builder<HomepagePage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * @param  Builder<HomepagePage>  $query
     * @return Builder<HomepagePage>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('title');
    }
}

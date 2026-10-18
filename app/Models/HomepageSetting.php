<?php

namespace App\Models;

use App\Enums\HomepageTemplate;
use Database\Factories\HomepageSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Einstellungen der öffentlichen Vereinshomepage (eine Zeile je Verein).
 */
class HomepageSetting extends Model
{
    /** @use HasFactory<HomepageSettingFactory> */
    use HasFactory;

    /**
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'enabled' => 'Homepage aktiv',
        'template' => 'Vorlage',
        'primary_color' => 'Hauptfarbe',
        'accent_color' => 'Akzentfarbe',
        'hero_title' => 'Überschrift',
        'hero_text' => 'Begrüßungstext',
        'about_html' => 'Über uns',
        'show_board' => 'Vorstand anzeigen',
        'show_board_photos' => 'Fotos des Vorstands anzeigen',
        'show_events' => 'Termine anzeigen',
        'contact_visible' => 'Kontaktdaten anzeigen',
        'domain' => 'Domain',
        'meta_description' => 'Beschreibung für Suchmaschinen',
        'hero_eyebrow' => 'Zeile über der Überschrift',
        'hero_button_label' => 'Zweiter Button',
        'announcement_enabled' => 'Ankündigung anzeigen',
        'announcement_title' => 'Ankündigung',
        'contact_text' => 'Text auf der Kontaktseite',
        'footer_info_title' => 'Info-Spalte in der Fußzeile',
        'show_member_area_link' => 'Link zum Mitgliederbereich',
        'tiles' => 'Kacheln',
        'stats' => 'Kennzahlen',
        'page_headings' => 'Seitenüberschriften',
    ];

    protected $table = 'tb_homepage_settings';

    protected $primaryKey = 'homepageSettingID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'template' => HomepageTemplate::class,
            'show_board' => 'boolean',
            'show_board_photos' => 'boolean',
            'show_events' => 'boolean',
            'contact_visible' => 'boolean',
            'announcement_enabled' => 'boolean',
            'show_member_area_link' => 'boolean',
            'tiles' => 'array',
            'stats' => 'array',
            'page_headings' => 'array',
        ];
    }

    /**
     * Bezeichnung der News im Menü (z. B. „Aktuelles“).
     */
    public function newsLabel(): string
    {
        return $this->pageHeading('news_menu') ?? 'News';
    }

    /**
     * Eigene Überschrift für den Kopf einer festen Seite (news, about, board, contact).
     */
    public function pageHeading(string $page): ?string
    {
        $heading = data_get($this->page_headings, $page);

        return filled($heading) ? $heading : null;
    }

    public static function current(): self
    {
        $settings = static::query()->firstOrCreate([], [
            'enabled' => false,
            'template' => HomepageTemplate::Klassisch,
        ]);

        // Neu angelegt: Standardwerte der Datenbank übernehmen.
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        $snapshot = $this->only(array_keys(self::ACTIVITY_LABELS));
        $snapshot['template'] = $this->template?->label();
        $snapshot['about_html'] = filled($this->about_html) ? Str::limit(strip_tags($this->about_html), 80) : null;
        $snapshot['tiles'] = collect($this->tiles ?? [])->pluck('title')->filter()->implode(', ') ?: null;
        $snapshot['stats'] = collect($this->stats ?? [])->map(fn (array $stat) => trim(($stat['value'] ?? '').' '.($stat['label'] ?? '')))->filter()->implode(', ') ?: null;
        $snapshot['page_headings'] = collect($this->page_headings ?? [])->filter()->implode(', ') ?: null;

        return $snapshot;
    }
}

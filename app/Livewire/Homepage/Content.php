<?php

namespace App\Livewire\Homepage;

use App\Enums\HomepageTemplate;
use App\Models\HomepageSetting;
use App\Services\ActivityLogger;
use App\Services\HomepageFileService;
use App\Services\HomepageImageService;
use App\Services\HomepageImageUrls;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Weitere Inhalte der Homepage: Elemente der Startseite (Ankündigung,
 * Kacheln, Kennzahlen), Überschriften der festen Seiten, Kontakttext und
 * Fußzeile. Genutzt von der Vorlage „Verein“.
 */
class Content extends Component
{
    use WithFileUploads;

    private const URL_PATTERN = '/^(https?:\/\/|mailto:|tel:|\/|#)\S*$/i';

    /**
     * Feste Seiten, deren Kopf eine eigene Überschrift bekommen kann.
     *
     * @var array<string, string>
     */
    public const HEADING_PAGES = [
        'news' => 'News',
        'about' => 'Über uns',
        'board' => 'Vorstand',
        'contact' => 'Kontakt',
    ];

    public string $heroEyebrow = '';

    public string $heroButtonLabel = '';

    public string $heroButtonUrl = '';

    public bool $announcementEnabled = false;

    public string $announcementBadge = '';

    public string $announcementTitle = '';

    public string $announcementText = '';

    public string $announcementLinkLabel = '';

    public string $announcementLinkUrl = '';

    public ?UploadedFile $announcementImage = null;

    public ?UploadedFile $announcementFile = null;

    /**
     * @var list<array{kicker: string, title: string, text: string, url: string, link_label: string}>
     */
    public array $tiles = [];

    /**
     * @var list<array{value: string, label: string}>
     */
    public array $stats = [];

    /**
     * @var array<string, string>
     */
    public array $pageHeadings = [];

    public string $contactText = '';

    public string $footerInfoTitle = '';

    public string $footerInfoText = '';

    public bool $showMemberAreaLink = false;

    public function mount(): void
    {
        $settings = HomepageSetting::current();

        $this->heroEyebrow = $settings->hero_eyebrow ?? '';
        $this->heroButtonLabel = $settings->hero_button_label ?? '';
        $this->heroButtonUrl = $settings->hero_button_url ?? '';
        $this->announcementEnabled = $settings->announcement_enabled;
        $this->announcementBadge = $settings->announcement_badge ?? '';
        $this->announcementTitle = $settings->announcement_title ?? '';
        $this->announcementText = $settings->announcement_text ?? '';
        $this->announcementLinkLabel = $settings->announcement_link_label ?? '';
        $this->announcementLinkUrl = $settings->announcement_link_url ?? '';
        $this->contactText = $settings->contact_text ?? '';
        $this->footerInfoTitle = $settings->footer_info_title ?? '';
        $this->footerInfoText = $settings->footer_info_text ?? '';
        $this->showMemberAreaLink = $settings->show_member_area_link;

        foreach (range(0, 2) as $index) {
            $this->tiles[] = [
                'kicker' => (string) data_get($settings->tiles, $index.'.kicker', ''),
                'title' => (string) data_get($settings->tiles, $index.'.title', ''),
                'text' => (string) data_get($settings->tiles, $index.'.text', ''),
                'url' => (string) data_get($settings->tiles, $index.'.url', ''),
                'link_label' => (string) data_get($settings->tiles, $index.'.link_label', ''),
            ];
        }

        foreach (range(0, 3) as $index) {
            $this->stats[] = [
                'value' => (string) data_get($settings->stats, $index.'.value', ''),
                'label' => (string) data_get($settings->stats, $index.'.label', ''),
            ];
        }

        foreach ([...array_keys(self::HEADING_PAGES), 'news_menu'] as $page) {
            $this->pageHeadings[$page] = (string) ($settings->pageHeading($page) ?? '');
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'heroEyebrow' => ['nullable', 'string', 'max:150'],
            'heroButtonLabel' => ['nullable', 'string', 'max:60'],
            'heroButtonUrl' => ['nullable', 'string', 'max:255', 'regex:'.self::URL_PATTERN],
            'announcementEnabled' => ['boolean'],
            'announcementBadge' => ['nullable', 'string', 'max:40'],
            'announcementTitle' => ['nullable', 'required_if:announcementEnabled,true', 'string', 'max:200'],
            'announcementText' => ['nullable', 'string', 'max:1000'],
            'announcementLinkLabel' => ['nullable', 'string', 'max:80'],
            'announcementLinkUrl' => ['nullable', 'string', 'max:255', 'regex:'.self::URL_PATTERN],
            'tiles' => ['array', 'size:3'],
            'tiles.*.kicker' => ['nullable', 'string', 'max:40'],
            'tiles.*.title' => ['nullable', 'string', 'max:120'],
            'tiles.*.text' => ['nullable', 'string', 'max:300'],
            'tiles.*.url' => ['nullable', 'string', 'max:255', 'regex:'.self::URL_PATTERN],
            'tiles.*.link_label' => ['nullable', 'string', 'max:60'],
            'stats' => ['array', 'size:4'],
            'stats.*.value' => ['nullable', 'string', 'max:20'],
            'stats.*.label' => ['nullable', 'string', 'max:80'],
            'pageHeadings' => ['array'],
            'pageHeadings.*' => ['nullable', 'string', 'max:200'],
            'contactText' => ['nullable', 'string', 'max:1000'],
            'footerInfoTitle' => ['nullable', 'string', 'max:60'],
            'footerInfoText' => ['nullable', 'string', 'max:300'],
            'showMemberAreaLink' => ['boolean'],
        ], [
            'regex' => 'Bitte eine vollständige Adresse (https://…), mailto:, tel: oder einen Pfad wie /kontakt angeben.',
        ], [
            'announcementTitle' => 'Titel der Ankündigung',
        ]);

        $settings = HomepageSetting::current();
        $before = $settings->activitySnapshot();

        $settings->update([
            'hero_eyebrow' => $this->nullIfBlank($validated['heroEyebrow']),
            'hero_button_label' => $this->nullIfBlank($validated['heroButtonLabel']),
            'hero_button_url' => $this->nullIfBlank($validated['heroButtonUrl']),
            'announcement_enabled' => $validated['announcementEnabled'],
            'announcement_badge' => $this->nullIfBlank($validated['announcementBadge']),
            'announcement_title' => $this->nullIfBlank($validated['announcementTitle']),
            'announcement_text' => $this->nullIfBlank($validated['announcementText']),
            'announcement_link_label' => $this->nullIfBlank($validated['announcementLinkLabel']),
            'announcement_link_url' => $this->nullIfBlank($validated['announcementLinkUrl']),
            'tiles' => array_values(array_filter(
                array_map(fn (array $tile) => array_map(fn ($value) => trim((string) $value), $tile), $validated['tiles']),
                fn (array $tile) => filled($tile['title'])
            )) ?: null,
            'stats' => array_values(array_filter(
                array_map(fn (array $stat) => array_map(fn ($value) => trim((string) $value), $stat), $validated['stats']),
                fn (array $stat) => filled($stat['value'])
            )) ?: null,
            'page_headings' => array_filter(array_map(fn ($heading) => trim((string) $heading), $validated['pageHeadings'])) ?: null,
            'contact_text' => $this->nullIfBlank($validated['contactText']),
            'footer_info_title' => $this->nullIfBlank($validated['footerInfoTitle']),
            'footer_info_text' => $this->nullIfBlank($validated['footerInfoText']),
            'show_member_area_link' => $validated['showMemberAreaLink'],
        ]);

        $changes = ActivityLogger::changes($before, $settings->fresh()->activitySnapshot(), HomepageSetting::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'homepage.content_updated',
                'Inhalte der Homepage wurden geändert.',
                'HomepageSetting',
                $settings->homepageSettingID,
                $changes
            );
        }

        session()->flash('success', 'Inhalte wurden gespeichert.');
    }

    public function updatedAnnouncementImage(HomepageImageService $images, HomepageFileService $files): void
    {
        $this->validate([
            'announcementImage' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
        ], [], ['announcementImage' => 'Bild der Ankündigung']);

        $this->replaceFile('announcement_image_path', function () use ($images): string {
            return $images->storeImage($this->announcementImage, 'homepage/announcement');
        }, 'announcementImage', $files);
    }

    public function updatedAnnouncementFile(HomepageFileService $files): void
    {
        $this->validate([
            'announcementFile' => ['file', 'mimes:pdf', 'max:'.HomepageFileService::MAX_SIZE_KB],
        ], [], ['announcementFile' => 'PDF der Ankündigung']);

        $this->replaceFile('announcement_file_path', function () use ($files): string {
            return $files->storePdf($this->announcementFile, 'homepage/announcement');
        }, 'announcementFile', $files);
    }

    public function removeAnnouncementMedia(string $kind, HomepageFileService $files): void
    {
        $column = $kind === 'file' ? 'announcement_file_path' : 'announcement_image_path';
        $settings = HomepageSetting::current();

        $files->delete($settings->{$column});
        $settings->update([$column => null]);
    }

    private function replaceFile(string $column, callable $store, string $property, HomepageFileService $files): void
    {
        $settings = HomepageSetting::current();

        try {
            $path = $store();
        } catch (RuntimeException $exception) {
            $this->addError($property, $exception->getMessage());

            return;
        } finally {
            $this->{$property} = null;
        }

        $files->delete($settings->{$column});
        $settings->update([$column => $path]);

        ActivityLogger::log('homepage.content_updated', 'Datei der Ankündigung auf der Homepage wurde geändert.', 'HomepageSetting', $settings->homepageSettingID);
    }

    private function nullIfBlank(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    public function render(HomepageImageUrls $imageUrls)
    {
        $settings = HomepageSetting::current();

        return view('livewire.homepage.content', [
            'usesVereinTemplate' => $settings->template === HomepageTemplate::Verein,
            'announcementImageUrl' => $imageUrls->announcementImage($settings),
            'announcementFileUrl' => filled($settings->announcement_file_path) ? $imageUrls->announcementLink($settings) : null,
            'headingPages' => self::HEADING_PAGES,
        ])->layout('layouts.app', [
            'title' => 'Homepage-Inhalte | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

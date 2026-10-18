<?php

namespace App\Livewire\Homepage;

use App\Models\HomepagePage;
use App\Models\HomepageSetting;
use App\Services\ActivityLogger;
use App\Services\HomepageFileService;
use App\Services\HomepageImageService;
use App\Services\HomepageImageUrls;
use App\Services\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Hinweise auf der Homepage: Cookie-Banner und Popup auf der Startseite.
 */
class Notices extends Component
{
    use WithFileUploads;

    private const URL_PATTERN = '/^(https?:\/\/|mailto:|tel:|\/|#)\S*$/i';

    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'cookie_banner_enabled' => 'Cookie-Hinweis anzeigen',
        'cookie_banner_text' => 'Text des Cookie-Hinweises',
        'cookie_banner_page_id' => 'Datenschutzseite',
        'popup_enabled' => 'Popup aktiv',
        'popup_title' => 'Popup-Titel',
        'popup_html' => 'Popup-Text',
        'popup_button_label' => 'Popup-Button',
        'popup_button_url' => 'Popup-Link',
        'popup_starts_at' => 'Popup ab',
        'popup_ends_at' => 'Popup bis',
        'popup_frequency' => 'Popup-Häufigkeit',
    ];

    /**
     * @var array<string, string>
     */
    public const FREQUENCIES = [
        'once' => 'Einmal pro Besucher (bis zur nächsten Änderung)',
        'session' => 'Einmal pro Besuch',
        'always' => 'Bei jedem Aufruf der Startseite',
    ];

    public bool $cookieBannerEnabled = true;

    public string $cookieBannerText = '';

    public ?int $cookieBannerPageID = null;

    public bool $popupEnabled = false;

    public string $popupTitle = '';

    public string $popupHtml = '';

    public string $popupButtonLabel = '';

    public string $popupButtonUrl = '';

    public string $popupStartsAt = '';

    public string $popupEndsAt = '';

    public string $popupFrequency = 'once';

    public ?UploadedFile $popupImage = null;

    public function mount(): void
    {
        $settings = HomepageSetting::current();

        $this->cookieBannerEnabled = $settings->cookie_banner_enabled;
        $this->cookieBannerText = $settings->cookie_banner_text ?? '';
        $this->cookieBannerPageID = $settings->cookie_banner_page_id;
        $this->popupEnabled = $settings->popup_enabled;
        $this->popupTitle = $settings->popup_title ?? '';
        $this->popupHtml = $settings->popup_html ?? '';
        $this->popupButtonLabel = $settings->popup_button_label ?? '';
        $this->popupButtonUrl = $settings->popup_button_url ?? '';
        $this->popupStartsAt = $settings->popup_starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->popupEndsAt = $settings->popup_ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->popupFrequency = $settings->popup_frequency ?: 'once';
    }

    public function save(HtmlSanitizer $sanitizer): void
    {
        $validated = $this->validate([
            'cookieBannerEnabled' => ['boolean'],
            'cookieBannerText' => ['nullable', 'string', 'max:600'],
            'cookieBannerPageID' => ['nullable', 'integer', Rule::exists('tb_homepage_pages', 'pageID')],
            'popupEnabled' => ['boolean'],
            'popupTitle' => ['nullable', 'required_if:popupEnabled,true', 'string', 'max:200'],
            'popupHtml' => ['nullable', 'string'],
            'popupButtonLabel' => ['nullable', 'string', 'max:80'],
            'popupButtonUrl' => ['nullable', 'required_with:popupButtonLabel', 'string', 'max:255', 'regex:'.self::URL_PATTERN],
            'popupStartsAt' => ['nullable', 'date'],
            'popupEndsAt' => ['nullable', 'date', 'after:popupStartsAt'],
            'popupFrequency' => [Rule::in(array_keys(self::FREQUENCIES))],
        ], [
            'popupButtonUrl.regex' => 'Bitte eine vollständige Adresse (https://…), mailto:, tel: oder einen Pfad wie /kontakt angeben.',
            'popupEndsAt.after' => 'Das Ende muss nach dem Beginn liegen.',
        ], [
            'cookieBannerText' => 'Text',
            'popupTitle' => 'Titel',
            'popupButtonLabel' => 'Button-Text',
            'popupButtonUrl' => 'Button-Link',
        ]);

        $settings = HomepageSetting::current();
        $before = $settings->only(array_keys(self::ACTIVITY_LABELS));

        $settings->fill([
            'cookie_banner_enabled' => $validated['cookieBannerEnabled'],
            'cookie_banner_text' => filled($validated['cookieBannerText']) ? trim($validated['cookieBannerText']) : null,
            'cookie_banner_page_id' => $validated['cookieBannerPageID'] ?: null,
            'popup_enabled' => $validated['popupEnabled'],
            'popup_title' => filled($validated['popupTitle']) ? $validated['popupTitle'] : null,
            'popup_html' => $sanitizer->sanitize($validated['popupHtml']) ?: null,
            'popup_button_label' => filled($validated['popupButtonLabel']) ? $validated['popupButtonLabel'] : null,
            'popup_button_url' => filled($validated['popupButtonUrl']) ? $validated['popupButtonUrl'] : null,
            'popup_starts_at' => filled($validated['popupStartsAt']) ? Carbon::parse($validated['popupStartsAt']) : null,
            'popup_ends_at' => filled($validated['popupEndsAt']) ? Carbon::parse($validated['popupEndsAt']) : null,
            'popup_frequency' => $validated['popupFrequency'],
        ]);

        // Inhalt geändert: Besucher, die das Popup schon geschlossen haben, sehen es erneut.
        if ($settings->isDirty(['popup_title', 'popup_html', 'popup_button_label', 'popup_button_url']) || ($settings->isDirty('popup_enabled') && $settings->popup_enabled)) {
            $settings->popup_version++;
        }

        $settings->save();

        $changes = ActivityLogger::changes($before, $settings->fresh()->only(array_keys(self::ACTIVITY_LABELS)), self::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log('homepage.notices_updated', 'Hinweise der Homepage (Cookie-Banner, Popup) wurden geändert.', 'HomepageSetting', $settings->homepageSettingID, $changes);
        }

        session()->flash('success', 'Hinweise wurden gespeichert.');
    }

    public function updatedPopupImage(HomepageImageService $images, HomepageFileService $files): void
    {
        $this->validate([
            'popupImage' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
        ], [], ['popupImage' => 'Bild']);

        $settings = HomepageSetting::current();

        try {
            $path = $images->storeImage($this->popupImage, 'homepage/popup');
        } catch (RuntimeException $exception) {
            $this->addError('popupImage', $exception->getMessage());

            return;
        } finally {
            $this->popupImage = null;
        }

        $files->delete($settings->popup_image_path);
        $settings->update(['popup_image_path' => $path, 'popup_version' => $settings->popup_version + 1]);

        session()->flash('success', 'Bild wurde gespeichert.');
    }

    public function removePopupImage(HomepageFileService $files): void
    {
        $settings = HomepageSetting::current();

        $files->delete($settings->popup_image_path);
        $settings->update(['popup_image_path' => null]);
    }

    public function render(HomepageImageUrls $imageUrls)
    {
        $settings = HomepageSetting::current();

        return view('livewire.homepage.notices', [
            'pages' => HomepagePage::query()->ordered()->get(['pageID', 'title', 'published']),
            'frequencies' => self::FREQUENCIES,
            'popupImageUrl' => $imageUrls->popupImage($settings),
            'popupIsActive' => $settings->popupIsActive(),
            'defaultCookieText' => HomepageSetting::DEFAULT_COOKIE_BANNER_TEXT,
        ])->layout('layouts.app', [
            'title' => 'Homepage-Hinweise | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

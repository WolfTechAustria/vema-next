<?php

namespace App\Livewire\Homepage;

use App\Enums\HomepageTemplate;
use App\Models\HomepageSetting;
use App\Models\TenantDomain;
use App\Services\ActivityLogger;
use App\Services\HomepageHosts;
use App\Services\HomepageImageService;
use App\Services\HomepageImageUrls;
use App\Services\HtmlSanitizer;
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Gestaltung und Grundeinstellungen der Vereinshomepage.
 */
class Settings extends Component
{
    use WithFileUploads;

    public bool $enabled = false;

    public string $template = 'klassisch';

    public string $primaryColor = '#1d4ed8';

    public string $accentColor = '#d7261e';

    public string $heroTitle = '';

    public string $heroText = '';

    public string $aboutHtml = '';

    public bool $showBoard = true;

    public bool $showBoardPhotos = false;

    public bool $showEvents = true;

    public bool $contactVisible = true;

    public string $domain = '';

    public string $metaDescription = '';

    public ?UploadedFile $heroImage = null;

    public function mount(): void
    {
        $settings = HomepageSetting::current();

        $this->enabled = $settings->enabled;
        $this->template = $settings->template->value;
        $this->primaryColor = $settings->primary_color;
        $this->accentColor = $settings->accent_color;
        $this->heroTitle = $settings->hero_title ?? '';
        $this->heroText = $settings->hero_text ?? '';
        $this->aboutHtml = $settings->about_html ?? '';
        $this->showBoard = $settings->show_board;
        $this->showBoardPhotos = $settings->show_board_photos;
        $this->showEvents = $settings->show_events;
        $this->contactVisible = $settings->contact_visible;
        $this->domain = $settings->domain ?? '';
        $this->metaDescription = $settings->meta_description ?? '';
    }

    public function save(HtmlSanitizer $sanitizer, HomepageHosts $hosts, TenantManager $tenantManager): void
    {
        $this->domain = (string) HomepageHosts::normalize($this->domain);

        $validated = $this->validate([
            'enabled' => ['boolean'],
            'template' => ['required', Rule::enum(HomepageTemplate::class)],
            'primaryColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accentColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'heroTitle' => ['nullable', 'string', 'max:200'],
            'heroText' => ['nullable', 'string', 'max:1000'],
            'aboutHtml' => ['nullable', 'string'],
            'showBoard' => ['boolean'],
            'showBoardPhotos' => ['boolean'],
            'showEvents' => ['boolean'],
            'contactVisible' => ['boolean'],
            'domain' => [
                'nullable',
                'string',
                'max:190',
                'regex:/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $appHosts = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

                    if (filled($value) && in_array(HomepageHosts::normalize($value), array_map(HomepageHosts::normalize(...), $appHosts), true)) {
                        $fail('Die Homepage braucht eine eigene Domain — nicht die Adresse von VEMA.');
                    }
                },
            ],
            'metaDescription' => ['nullable', 'string', 'max:300'],
        ], [
            'domain.regex' => 'Bitte eine gültige Domain angeben, z. B. mein-verein.at.',
        ], [
            'primaryColor' => 'Hauptfarbe',
            'accentColor' => 'Akzentfarbe',
            'heroTitle' => 'Überschrift',
            'heroText' => 'Begrüßungstext',
            'domain' => 'Domain',
            'metaDescription' => 'Beschreibung',
        ]);

        $settings = HomepageSetting::current();
        $before = $settings->activitySnapshot();

        $settings->update([
            'enabled' => $validated['enabled'],
            'template' => $validated['template'],
            'primary_color' => strtolower($validated['primaryColor']),
            'accent_color' => strtolower($validated['accentColor']),
            'hero_title' => filled($validated['heroTitle']) ? $validated['heroTitle'] : null,
            'hero_text' => filled($validated['heroText']) ? $validated['heroText'] : null,
            'about_html' => $sanitizer->sanitize($validated['aboutHtml']) ?: null,
            'show_board' => $validated['showBoard'],
            'show_board_photos' => $validated['showBoardPhotos'],
            'show_events' => $validated['showEvents'],
            'contact_visible' => $validated['contactVisible'],
            // Im Modus "multi" verwaltet die Plattform die Domains.
            'domain' => $tenantManager->isMultiTenant() ? $settings->domain : ($validated['domain'] ?: null),
            'meta_description' => filled($validated['metaDescription']) ? $validated['metaDescription'] : null,
        ]);

        $hosts->forgetSingleDomain();

        $changes = ActivityLogger::changes($before, $settings->fresh()->activitySnapshot(), HomepageSetting::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'homepage.settings_updated',
                'Einstellungen der Homepage wurden geändert.',
                'HomepageSetting',
                $settings->homepageSettingID,
                $changes
            );
        }

        session()->flash('success', 'Einstellungen wurden gespeichert.');
    }

    public function updatedHeroImage(HomepageImageService $images): void
    {
        $this->validate([
            'heroImage' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
        ], [], ['heroImage' => 'Titelbild']);

        try {
            $images->storeHeroImage(HomepageSetting::current(), $this->heroImage);
        } catch (RuntimeException $exception) {
            $this->addError('heroImage', $exception->getMessage());

            return;
        } finally {
            $this->heroImage = null;
        }

        ActivityLogger::log('homepage.hero_updated', 'Titelbild der Homepage wurde geändert.', 'HomepageSetting', HomepageSetting::current()->homepageSettingID);

        session()->flash('success', 'Titelbild wurde gespeichert.');
    }

    public function removeHeroImage(HomepageImageService $images): void
    {
        $images->removeHeroImage(HomepageSetting::current());

        ActivityLogger::log('homepage.hero_removed', 'Titelbild der Homepage wurde entfernt.', 'HomepageSetting', HomepageSetting::current()->homepageSettingID);

        session()->flash('success', 'Titelbild wurde entfernt.');
    }

    public function render(TenantManager $tenantManager, HomepageImageUrls $imageUrls)
    {
        $settings = HomepageSetting::current();
        $tenant = $tenantManager->current();

        $platformDomains = $tenant === null
            ? []
            : TenantDomain::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('purpose', HomepageHosts::PURPOSE)
                ->orderBy('domain')
                ->pluck('domain')
                ->all();

        return view('livewire.homepage.settings', [
            'templates' => HomepageTemplate::cases(),
            'heroUrl' => $imageUrls->hero($settings),
            'isMultiTenant' => $tenantManager->isMultiTenant(),
            'platformDomains' => $platformDomains,
            'savedDomain' => $settings->domain,
        ])->layout('layouts.app', [
            'title' => 'Homepage-Gestaltung | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

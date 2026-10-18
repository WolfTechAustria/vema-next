<?php

namespace App\Livewire\Homepage;

use App\Enums\HomepageSectionType;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Services\ActivityLogger;
use App\Services\HomepageFileService;
use App\Services\HomepageImageService;
use App\Services\HomepageImageUrls;
use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Abschnitts-Baukasten für eine eigene Seite oder die feste Seite „Über uns“.
 */
class Sections extends Component
{
    use WithFileUploads;

    private const URL_PATTERN = '/^(https?:\/\/|mailto:|tel:|\/|#)\S*$/i';

    public ?int $pageID = null;

    public ?int $editingSectionID = null;

    /**
     * Erzwingt nach dem Wechsel des Abschnitts einen frischen Editor (wire:ignore).
     */
    public int $formVersion = 0;

    public string $type = 'text';

    public string $heading = '';

    public string $bodyHtml = '';

    public bool $altBackground = false;

    public string $imagePosition = 'right';

    /**
     * Kacheln (Zahlen-Kacheln) bzw. Bildunterschriften (Bilderpaar).
     *
     * @var list<array{value?: string, title?: string, text?: string}>
     */
    public array $items = [];

    public string $boxTitle = '';

    public string $boxValue = '';

    public string $boxNote = '';

    public string $boxText = '';

    public string $buttonLabel = '';

    public string $buttonUrl = '';

    public bool $buttonToMemberArea = false;

    /**
     * Anzahl der Termine im Abschnitt „Termine“.
     */
    public int $eventLimit = 5;

    public ?UploadedFile $image = null;

    public ?UploadedFile $image2 = null;

    public ?UploadedFile $file = null;

    public function mount(?HomepagePage $page = null): void
    {
        $this->pageID = $page?->exists ? $page->pageID : null;
    }

    public function addSection(string $type): void
    {
        $sectionType = HomepageSectionType::from($type);

        $section = HomepageSection::create([
            'pageID' => $this->pageID,
            'area' => $this->area(),
            'type' => $sectionType,
            'sort' => (int) $this->sectionsQuery()->max('sort') + 1,
            'data' => $this->defaultData($sectionType),
        ]);

        $this->logChange('homepage.section_created', 'Abschnitt „'.$sectionType->label().'“ wurde hinzugefügt.', $section);

        $this->editSection($section->sectionID);
    }

    public function editSection(int $sectionID): void
    {
        $section = $this->findSection($sectionID);

        $this->resetForm();

        $this->editingSectionID = $section->sectionID;
        $this->type = $section->type->value;
        $this->heading = $section->heading ?? '';
        $this->bodyHtml = $section->body_html ?? '';
        $this->altBackground = $section->alt_background;
        $this->imagePosition = $section->value('image_position', 'right');
        $this->items = $this->normalizeItems($section->type, $section->value('items', []));
        $this->boxTitle = (string) $section->value('box_title', '');
        $this->boxValue = (string) $section->value('box_value', '');
        $this->boxNote = (string) $section->value('box_note', '');
        $this->boxText = (string) $section->value('box_text', '');
        $this->buttonLabel = (string) $section->value('button_label', '');
        $this->buttonUrl = (string) $section->value('button_url', '');
        $this->buttonToMemberArea = (bool) $section->value('button_member_area', false);
        $this->eventLimit = (int) $section->value('limit', 5);
    }

    public function saveSection(HtmlSanitizer $sanitizer, HomepageImageService $images, HomepageFileService $files): void
    {
        $section = $this->findSection((int) $this->editingSectionID);

        $validated = $this->validate([
            'heading' => ['nullable', 'string', 'max:200'],
            'bodyHtml' => ['nullable', 'string'],
            'altBackground' => ['boolean'],
            'imagePosition' => [Rule::in(['left', 'right'])],
            'items' => ['array', 'max:4'],
            'items.*.value' => ['nullable', 'string', 'max:20'],
            'items.*.title' => ['nullable', 'string', 'max:120'],
            'items.*.text' => ['nullable', 'string', 'max:500'],
            'boxTitle' => ['nullable', 'string', 'max:120'],
            'boxValue' => ['nullable', 'string', 'max:40'],
            'boxNote' => ['nullable', 'string', 'max:120'],
            'boxText' => ['nullable', 'string', 'max:1000'],
            'buttonLabel' => ['nullable', 'string', 'max:80'],
            'buttonUrl' => ['nullable', 'string', 'max:255', 'regex:'.self::URL_PATTERN],
            'buttonToMemberArea' => ['boolean'],
            'eventLimit' => ['integer', 'min:1', 'max:10'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
            'image2' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.HomepageImageService::MAX_SIZE_KB],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:'.HomepageFileService::MAX_SIZE_KB],
        ], [
            'buttonUrl.regex' => 'Bitte eine vollständige Adresse (https://…), mailto:, tel: oder einen Pfad wie /kontakt angeben.',
        ], [
            'heading' => 'Überschrift',
            'buttonUrl' => 'Button-Link',
            'buttonLabel' => 'Button-Text',
            'image' => 'Bild',
            'image2' => 'zweites Bild',
            'file' => 'PDF',
        ]);

        $attributes = [
            'heading' => filled($validated['heading']) ? $validated['heading'] : null,
            'body_html' => $sanitizer->sanitize($validated['bodyHtml']) ?: null,
            'alt_background' => $validated['altBackground'],
            'data' => array_filter([
                'image_position' => $validated['imagePosition'],
                'items' => array_values(array_map(
                    fn (array $item) => array_map(fn ($value) => trim((string) $value), $item),
                    $validated['items']
                )),
                'box_title' => $validated['boxTitle'],
                'box_value' => $validated['boxValue'],
                'box_note' => $validated['boxNote'],
                'box_text' => $validated['boxText'],
                'button_label' => $validated['buttonLabel'],
                'button_url' => $validated['buttonUrl'],
                'button_member_area' => $validated['buttonToMemberArea'],
                'limit' => $section->type === HomepageSectionType::Events ? $validated['eventLimit'] : null,
            ], fn ($value) => $value !== '' && $value !== null && $value !== [] && $value !== false),
        ];

        try {
            if ($this->image) {
                $path = $images->storeImage($this->image, $this->mediaDirectory());
                $this->deleteFile($section->image_path);
                $attributes['image_path'] = $path;
            }

            if ($this->image2) {
                $path = $images->storeImage($this->image2, $this->mediaDirectory());
                $this->deleteFile($section->image2_path);
                $attributes['image2_path'] = $path;
            }

            if ($this->file) {
                $path = $files->storePdf($this->file, $this->mediaDirectory());
                $files->delete($section->file_path);
                $attributes['file_path'] = $path;
            }
        } catch (RuntimeException $exception) {
            $this->addError('file', $exception->getMessage());

            return;
        }

        $section->update($attributes);

        $this->logChange('homepage.section_updated', 'Abschnitt „'.$section->type->label().'“ wurde geändert.', $section);

        $this->resetForm();

        session()->flash('success', 'Abschnitt wurde gespeichert.');
    }

    public function removeMedia(string $kind, HomepageFileService $files): void
    {
        $section = $this->findSection((int) $this->editingSectionID);

        $column = match ($kind) {
            'image' => 'image_path',
            'image2' => 'image2_path',
            default => 'file_path',
        };

        $files->delete($section->{$column});
        $section->update([$column => null]);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function moveSection(int $sectionID, string $direction): void
    {
        $sections = $this->sectionsQuery()->get()->values();
        $index = $sections->search(fn (HomepageSection $section) => $section->sectionID === $sectionID);

        abort_if($index === false, 404);

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! $sections->has($target)) {
            return;
        }

        $ordered = $sections->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $section) {
            $section->update(['sort' => $position + 1]);
        }
    }

    public function deleteSection(int $sectionID): void
    {
        $section = $this->findSection($sectionID);
        $section->deleteWithFiles();

        $this->logChange('homepage.section_deleted', 'Abschnitt „'.$section->type->label().'“ wurde gelöscht.', $section);

        if ($this->editingSectionID === $sectionID) {
            $this->resetForm();
        }

        session()->flash('success', 'Abschnitt wurde gelöscht.');
    }

    private function findSection(int $sectionID): HomepageSection
    {
        $section = $this->sectionsQuery()->whereKey($sectionID)->first();

        abort_if($section === null, 404);

        return $section;
    }

    /**
     * @return Builder<HomepageSection>
     */
    private function sectionsQuery(): Builder
    {
        return HomepageSection::query()
            ->where('area', $this->area())
            ->when(
                $this->pageID,
                fn (Builder $query) => $query->where('pageID', $this->pageID),
                fn (Builder $query) => $query->whereNull('pageID')
            )
            ->orderBy('sort')
            ->orderBy('sectionID');
    }

    private function area(): string
    {
        return $this->pageID ? HomepageSection::AREA_PAGE : HomepageSection::AREA_ABOUT;
    }

    private function mediaDirectory(): string
    {
        return 'homepage/sections/'.($this->pageID ?? 'about');
    }

    private function deleteFile(?string $path): void
    {
        app(HomepageFileService::class)->delete($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultData(HomepageSectionType $type): array
    {
        return match ($type) {
            HomepageSectionType::Features => ['items' => array_fill(0, 3, ['value' => '', 'title' => '', 'text' => ''])],
            HomepageSectionType::ImagePair => ['items' => array_fill(0, 2, ['title' => '', 'text' => ''])],
            HomepageSectionType::Events => ['limit' => 5],
            default => [],
        };
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array<string, string>>
     */
    private function normalizeItems(HomepageSectionType $type, array $items): array
    {
        $count = match ($type) {
            HomepageSectionType::Features => max(1, min(4, count($items) ?: 3)),
            HomepageSectionType::ImagePair => 2,
            default => 0,
        };

        $normalized = [];

        for ($index = 0; $index < $count; $index++) {
            $normalized[] = [
                'value' => (string) data_get($items, $index.'.value', ''),
                'title' => (string) data_get($items, $index.'.title', ''),
                'text' => (string) data_get($items, $index.'.text', ''),
            ];
        }

        return $normalized;
    }

    public function addItem(): void
    {
        if (count($this->items) < 4) {
            $this->items[] = ['value' => '', 'title' => '', 'text' => ''];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingSectionID', 'type', 'heading', 'bodyHtml', 'altBackground', 'imagePosition', 'items',
            'boxTitle', 'boxValue', 'boxNote', 'boxText', 'buttonLabel', 'buttonUrl', 'buttonToMemberArea', 'eventLimit',
            'image', 'image2', 'file',
        ]);
        $this->resetValidation();
        $this->formVersion++;
    }

    private function logChange(string $action, string $message, HomepageSection $section): void
    {
        $page = $this->pageID ? HomepagePage::find($this->pageID) : null;

        ActivityLogger::log(
            $action,
            $message.' (Seite „'.($page?->title ?? 'Über uns').'“)',
            'HomepageSection',
            $section->sectionID
        );
    }

    public function render(HomepageImageUrls $imageUrls)
    {
        $page = $this->pageID ? HomepagePage::find($this->pageID) : null;

        /** @var Collection<int, HomepageSection> $sections */
        $sections = $this->sectionsQuery()->get();

        return view('livewire.homepage.sections', [
            'page' => $page,
            'sections' => $sections,
            'editingSection' => $this->editingSectionID ? $sections->firstWhere('sectionID', $this->editingSectionID) : null,
            'sectionTypes' => HomepageSectionType::cases(),
            'imageUrls' => $imageUrls,
        ])->layout('layouts.app', [
            'title' => 'Seitenabschnitte | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

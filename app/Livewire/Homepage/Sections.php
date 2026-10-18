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

    /**
     * @var array<string, string>
     */
    public const SPACINGS = [
        'none' => 'Kein',
        'small' => 'Klein',
        'normal' => 'Normal',
        'large' => 'Groß',
    ];

    /**
     * Breitenverhältnis der Spalten.
     *
     * @var array<string, string>
     */
    public const RATIOS = [
        '1-1' => 'Gleich breit',
        '2-1' => 'Links breiter',
        '1-2' => 'Rechts breiter',
    ];

    /**
     * @var array<string, string>
     */
    public const LIST_STYLES = [
        'numbered' => 'Nummerierte Schritte',
        'bullets' => 'Punkte',
        'check' => 'Häkchen',
    ];

    /**
     * Breite des Abschnitts „Bild“.
     *
     * @var array<string, string>
     */
    public const IMAGE_WIDTHS = [
        'narrow' => 'Schmal',
        'normal' => 'Normal',
        'wide' => 'Volle Breite',
    ];

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

    /**
     * Abstand über und unter dem Abschnitt: none, small, normal, large.
     */
    public string $spacing = 'normal';

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

    public string $columnsRatio = '1-1';

    public string $columnsAlign = 'top';

    /**
     * Info-Box mit Rahmen (Hintergrund, Linie, Schatten) oder schlicht.
     */
    public bool $boxFramed = true;

    /**
     * Punkte des Abschnitts „Titel mit Aufzählung“.
     *
     * @var list<array{title: string, text: string}>
     */
    public array $listItems = [];

    public string $listStyle = 'numbered';

    public string $imageCaption = '';

    public string $imageWidth = 'normal';

    public ?UploadedFile $image = null;

    public ?UploadedFile $image2 = null;

    public ?UploadedFile $file = null;

    public function mount(?HomepagePage $page = null): void
    {
        $this->pageID = $page?->exists ? $page->pageID : null;
    }

    /**
     * Neuer Abschnitt – auf oberster Ebene oder in einer Spalte (links/rechts)
     * eines Abschnitts „Zwei Spalten“.
     */
    public function addSection(string $type, ?int $parentID = null, ?string $column = null): void
    {
        $sectionType = HomepageSectionType::from($type);
        $parent = null;

        if ($parentID !== null) {
            $parent = $this->findSection($parentID);

            abort_unless(
                $parent->type->isContainer() && ! $sectionType->isContainer() && in_array($column, ['left', 'right'], true),
                422
            );
        }

        $section = HomepageSection::create([
            'pageID' => $this->pageID,
            'area' => $this->area(),
            'parentID' => $parent?->sectionID,
            'column' => $parent ? $column : null,
            'type' => $sectionType,
            'sort' => (int) $this->siblingsQuery($parent?->sectionID, $parent ? $column : null)->max('sort') + 1,
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
        $this->spacing = (string) $section->value('spacing', 'normal');
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
        $this->columnsRatio = (string) $section->value('ratio', '1-1');
        $this->columnsAlign = (string) $section->value('align', 'top');
        $this->boxFramed = ! $section->value('box_frameless', false);
        $this->listStyle = (string) $section->value('list_style', 'numbered');
        $this->imageCaption = (string) $section->value('caption', '');
        $this->imageWidth = (string) $section->value('width', 'normal');
        $this->listItems = collect($section->value('list_items', []))
            ->map(fn ($item) => ['title' => (string) data_get($item, 'title', ''), 'text' => (string) data_get($item, 'text', '')])
            ->values()
            ->all();

        if ($section->type === HomepageSectionType::ItemList && $this->listItems === []) {
            $this->listItems = array_fill(0, 3, ['title' => '', 'text' => '']);
        }
    }

    public function saveSection(HtmlSanitizer $sanitizer, HomepageImageService $images, HomepageFileService $files): void
    {
        $section = $this->findSection((int) $this->editingSectionID);

        $validated = $this->validate([
            'heading' => ['nullable', 'string', 'max:200'],
            'bodyHtml' => ['nullable', 'string'],
            'altBackground' => ['boolean'],
            'spacing' => [Rule::in(array_keys(self::SPACINGS))],
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
            'columnsRatio' => [Rule::in(array_keys(self::RATIOS))],
            'columnsAlign' => [Rule::in(['top', 'center'])],
            'boxFramed' => ['boolean'],
            'listItems' => ['array', 'max:12'],
            'listItems.*.title' => ['nullable', 'string', 'max:150'],
            'listItems.*.text' => ['nullable', 'string', 'max:600'],
            'listStyle' => [Rule::in(array_keys(self::LIST_STYLES))],
            'imageCaption' => ['nullable', 'string', 'max:300'],
            'imageWidth' => [Rule::in(array_keys(self::IMAGE_WIDTHS))],
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
                'spacing' => $validated['spacing'] === 'normal' ? null : $validated['spacing'],
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
                'ratio' => $section->type === HomepageSectionType::Columns ? $validated['columnsRatio'] : null,
                'align' => $section->type === HomepageSectionType::Columns ? $validated['columnsAlign'] : null,
                'box_frameless' => in_array($section->type, [HomepageSectionType::TextBox, HomepageSectionType::InfoBox], true) && ! $validated['boxFramed'],
                'list_style' => $section->type === HomepageSectionType::ItemList ? $validated['listStyle'] : null,
                'caption' => $section->type === HomepageSectionType::Image ? trim((string) $validated['imageCaption']) : null,
                'width' => $section->type === HomepageSectionType::Image && $validated['imageWidth'] !== 'normal' ? $validated['imageWidth'] : null,
                'list_items' => $section->type === HomepageSectionType::ItemList
                    ? array_values(array_filter(
                        array_map(fn (array $item) => ['title' => trim((string) ($item['title'] ?? '')), 'text' => trim((string) ($item['text'] ?? ''))], $validated['listItems']),
                        fn (array $item) => $item['title'] !== '' || $item['text'] !== ''
                    ))
                    : null,
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
        $moved = $this->findSection($sectionID);
        $sections = $this->siblingsQuery($moved->parentID, $moved->column)->get()->values();
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

    /**
     * Abschnitt in die andere Spalte legen (ans Ende).
     */
    public function switchColumn(int $sectionID): void
    {
        $section = $this->findSection($sectionID);

        abort_if($section->parentID === null, 404);

        $target = $section->column === 'left' ? 'right' : 'left';

        $section->update([
            'column' => $target,
            'sort' => (int) $this->siblingsQuery($section->parentID, $target)->max('sort') + 1,
        ]);
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

    /**
     * Abschnitte derselben Ebene: oberste Ebene oder eine Spalte eines Spalten-Abschnitts.
     *
     * @return Builder<HomepageSection>
     */
    private function siblingsQuery(?int $parentID, ?string $column): Builder
    {
        return $this->sectionsQuery()
            ->when(
                $parentID,
                fn (Builder $query) => $query->where('parentID', $parentID)->where('column', $column),
                fn (Builder $query) => $query->whereNull('parentID')
            );
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
            HomepageSectionType::Columns => ['ratio' => '1-1', 'align' => 'top'],
            HomepageSectionType::ItemList => ['list_style' => 'numbered'],
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

    public function addListItem(): void
    {
        if (count($this->listItems) < 12) {
            $this->listItems[] = ['title' => '', 'text' => ''];
        }
    }

    public function removeListItem(int $index): void
    {
        unset($this->listItems[$index]);
        $this->listItems = array_values($this->listItems);
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
            'editingSectionID', 'type', 'heading', 'bodyHtml', 'altBackground', 'spacing', 'imagePosition', 'items',
            'boxTitle', 'boxValue', 'boxNote', 'boxText', 'buttonLabel', 'buttonUrl', 'buttonToMemberArea', 'eventLimit',
            'columnsRatio', 'columnsAlign', 'boxFramed', 'listItems', 'listStyle', 'imageCaption', 'imageWidth',
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
        $sections = $this->sectionsQuery()->whereNull('parentID')->with('children')->get();
        $editingSection = $this->editingSectionID ? $this->sectionsQuery()->whereKey($this->editingSectionID)->first() : null;

        return view('livewire.homepage.sections', [
            'page' => $page,
            'sections' => $sections,
            'editingSection' => $editingSection,
            'ratios' => self::RATIOS,
            'listStyles' => self::LIST_STYLES,
            'imageWidths' => self::IMAGE_WIDTHS,
            'sectionTypes' => HomepageSectionType::cases(),
            'spacings' => self::SPACINGS,
            'imageUrls' => $imageUrls,
        ])->layout('layouts.app', [
            'title' => 'Seitenabschnitte | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

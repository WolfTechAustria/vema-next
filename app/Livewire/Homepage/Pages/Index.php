<?php

namespace App\Livewire\Homepage\Pages;

use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Services\ActivityLogger;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Frei anlegbare Seiten der Homepage (Impressum, Geschichte …).
 */
class Index extends Component
{
    public string $title = '';

    public string $subtitle = '';

    public string $slug = '';

    public string $bodyHtml = '';

    public int $sort = 0;

    public bool $inMenu = true;

    public bool $published = false;

    public ?int $editingPageID = null;

    /**
     * Erzwingt nach dem Speichern einen frischen Editor (wire:ignore).
     */
    public int $formVersion = 0;

    public function updatedTitle(): void
    {
        if ($this->editingPageID === null) {
            $this->slug = Str::slug($this->title, '-', 'de');
        }
    }

    public function savePage(HtmlSanitizer $sanitizer): void
    {
        $this->slug = Str::slug($this->slug, '-', 'de');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(HomepagePage::RESERVED_SLUGS),
                Rule::unique('tb_homepage_pages', 'slug')->ignore($this->editingPageID, 'pageID'),
            ],
            'bodyHtml' => ['nullable', 'string'],
            'sort' => ['integer', 'min:0', 'max:999'],
            'inMenu' => ['boolean'],
            'published' => ['boolean'],
        ], [
            'slug.not_in' => 'Diese Adresse ist für eine feste Seite der Homepage reserviert.',
        ], [
            'title' => 'Titel',
            'subtitle' => 'Überschrift im Seitenkopf',
            'slug' => 'Adresse',
            'sort' => 'Reihenfolge',
        ]);

        $attributes = [
            'title' => $validated['title'],
            'subtitle' => filled($validated['subtitle']) ? $validated['subtitle'] : null,
            'slug' => $validated['slug'],
            'body_html' => $sanitizer->sanitize($validated['bodyHtml']) ?: null,
            'sort' => $validated['sort'],
            'in_menu' => $validated['inMenu'],
            'published' => $validated['published'],
        ];

        if ($this->editingPageID === null) {
            $page = HomepagePage::create($attributes);

            ActivityLogger::log(
                'homepage.page_created',
                'Homepage-Seite „'.$page->title.'“ wurde angelegt.',
                'HomepagePage',
                $page->pageID,
                ActivityLogger::created($page->fresh()->only(array_keys(HomepagePage::ACTIVITY_LABELS)), HomepagePage::ACTIVITY_LABELS)
            );

            session()->flash('success', 'Seite wurde angelegt.');
        } else {
            $page = HomepagePage::findOrFail($this->editingPageID);
            $before = $page->only(array_keys(HomepagePage::ACTIVITY_LABELS));
            $bodyChanged = $page->body_html !== $attributes['body_html'];

            $page->update($attributes);

            $changes = ActivityLogger::changes($before, $page->fresh()->only(array_keys(HomepagePage::ACTIVITY_LABELS)), HomepagePage::ACTIVITY_LABELS);

            if ($changes !== [] || $bodyChanged) {
                ActivityLogger::log(
                    'homepage.page_updated',
                    'Homepage-Seite „'.$page->title.'“ wurde geändert.',
                    'HomepagePage',
                    $page->pageID,
                    $changes
                );
            }

            session()->flash('success', 'Seite wurde gespeichert.');
        }

        $this->resetForm();
    }

    public function editPage(int $pageID): void
    {
        $page = HomepagePage::findOrFail($pageID);

        $this->editingPageID = $page->pageID;
        $this->title = $page->title;
        $this->subtitle = $page->subtitle ?? '';
        $this->slug = $page->slug;
        $this->bodyHtml = $page->body_html ?? '';
        $this->sort = $page->sort;
        $this->inMenu = $page->in_menu;
        $this->published = $page->published;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function deletePage(int $pageID): void
    {
        $page = HomepagePage::findOrFail($pageID);
        $page->deleteWithSections();

        ActivityLogger::log(
            'homepage.page_deleted',
            'Homepage-Seite „'.$page->title.'“ wurde gelöscht.',
            'HomepagePage',
            $pageID
        );

        if ($this->editingPageID === $pageID) {
            $this->resetForm();
        }

        session()->flash('success', 'Seite wurde gelöscht.');
    }

    private function resetForm(): void
    {
        $this->reset(['title', 'subtitle', 'slug', 'bodyHtml', 'sort', 'inMenu', 'published', 'editingPageID']);
        $this->formVersion++;
    }

    public function render()
    {
        return view('livewire.homepage.pages.index', [
            'pages' => HomepagePage::query()->withCount('sections')->ordered()->get(),
            'aboutSectionsCount' => HomepageSection::query()->forAbout()->count(),
        ])->layout('layouts.app', [
            'title' => 'Homepage-Seiten | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

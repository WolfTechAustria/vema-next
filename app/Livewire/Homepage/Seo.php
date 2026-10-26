<?php

namespace App\Livewire\Homepage;

use App\Models\HomepageNotFound;
use App\Models\HomepageRedirect;
use App\Services\ActivityLogger;
use App\Services\HomepageHosts;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Suchmaschinen: Sitemap-Adresse, Weiterleitungen alter Adressen und die
 * Liste der Adressen, die auf der Homepage nicht gefunden wurden.
 */
class Seo extends Component
{
    private const TARGET_PATTERN = '#^(/[^\s]*|https?://[^\s]+)$#i';

    public string $sourcePath = '';

    public string $target = '';

    public ?int $editingRedirectID = null;

    public function saveRedirect(): void
    {
        $this->sourcePath = HomepageRedirect::normalizePath($this->sourcePath);
        $this->target = trim($this->target);

        $validated = $this->validate([
            'sourcePath' => [
                'required',
                'string',
                'max:255',
                'not_in:/',
                Rule::unique('tb_homepage_redirects', 'source_path')->ignore($this->editingRedirectID, 'redirectID'),
            ],
            'target' => ['required', 'string', 'max:500', 'regex:'.self::TARGET_PATTERN, 'different:sourcePath'],
        ], [
            'sourcePath.not_in' => 'Die Startseite kann nicht weitergeleitet werden.',
            'sourcePath.unique' => 'Für diese Adresse gibt es schon eine Weiterleitung.',
            'target.regex' => 'Bitte eine Adresse der Homepage wie „/news“ oder eine vollständige Adresse mit https:// angeben.',
            'target.different' => 'Ziel und alte Adresse dürfen nicht gleich sein.',
        ], [
            'sourcePath' => 'Alte Adresse',
            'target' => 'Ziel',
        ]);

        $attributes = ['source_path' => $validated['sourcePath'], 'target' => $validated['target']];

        if ($this->editingRedirectID === null) {
            $redirect = HomepageRedirect::create($attributes);
            $message = 'Weiterleitung von „'.$redirect->source_path.'“ nach „'.$redirect->target.'“ wurde angelegt.';
        } else {
            $redirect = HomepageRedirect::findOrFail($this->editingRedirectID);
            $redirect->update($attributes);
            $message = 'Weiterleitung von „'.$redirect->source_path.'“ nach „'.$redirect->target.'“ wurde geändert.';
        }

        HomepageNotFound::query()->where('path', $redirect->source_path)->delete();

        ActivityLogger::log('homepage.redirect_saved', $message, 'HomepageRedirect', $redirect->redirectID);

        session()->flash('success', 'Weiterleitung wurde gespeichert.');

        $this->resetForm();
    }

    public function editRedirect(int $redirectID): void
    {
        $redirect = HomepageRedirect::findOrFail($redirectID);

        $this->editingRedirectID = $redirect->redirectID;
        $this->sourcePath = $redirect->source_path;
        $this->target = $redirect->target;

        $this->resetValidation();
    }

    public function deleteRedirect(int $redirectID): void
    {
        $redirect = HomepageRedirect::findOrFail($redirectID);
        $redirect->delete();

        ActivityLogger::log(
            'homepage.redirect_deleted',
            'Weiterleitung von „'.$redirect->source_path.'“ wurde gelöscht.',
            'HomepageRedirect',
            $redirectID
        );

        if ($this->editingRedirectID === $redirectID) {
            $this->resetForm();
        }

        session()->flash('success', 'Weiterleitung wurde gelöscht.');
    }

    /**
     * Nicht gefundene Adresse als neue Weiterleitung übernehmen.
     */
    public function redirectNotFound(int $notFoundID): void
    {
        $entry = HomepageNotFound::findOrFail($notFoundID);

        $this->resetForm();
        $this->resetValidation();
        $this->sourcePath = $entry->path;
    }

    public function dismissNotFound(int $notFoundID): void
    {
        HomepageNotFound::query()->whereKey($notFoundID)->delete();
    }

    public function clearNotFound(): void
    {
        HomepageNotFound::query()->delete();

        session()->flash('success', 'Liste der nicht gefundenen Adressen wurde geleert.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset(['sourcePath', 'target', 'editingRedirectID']);
    }

    public function render(HomepageHosts $hosts)
    {
        return view('livewire.homepage.seo', [
            'redirects' => HomepageRedirect::query()->orderBy('source_path')->get(),
            'notFound' => HomepageNotFound::query()->orderByDesc('hits')->orderByDesc('last_seen_at')->limit(100)->get(),
            'publicRoot' => $hosts->publicRoot(),
            'sitemapUrl' => $hosts->publicRoute('homepage.sitemap'),
        ])->layout('layouts.app', [
            'title' => 'Homepage-Suchmaschinen | VEMA',
            'heading' => 'Homepage',
        ]);
    }
}

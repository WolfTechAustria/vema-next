<?php

namespace App\Livewire\News;

use App\Services\PlatformNewsFeed;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Neuigkeiten zu VEMA für die Admins eines Vereins (nur Plattformbetrieb).
 * Beim Öffnen gilt alles als gelesen; „neu“ bleibt bis zum nächsten Besuch markiert.
 */
class Index extends Component
{
    /** Gelesen bis (beim Öffnen der Seite), für die „neu“-Markierung */
    public ?string $seenBefore = null;

    public function mount(PlatformNewsFeed $feed): void
    {
        abort_unless($feed->isAvailableFor(auth()->user()), 404);

        $this->seenBefore = $feed->seenAt(auth()->user())?->toIso8601String();

        $feed->markSeen(auth()->user());
    }

    public function render(PlatformNewsFeed $feed)
    {
        $seenBefore = $this->seenBefore ? Carbon::parse($this->seenBefore) : null;

        return view('livewire.news.index', [
            'newsItems' => $feed->news(),
            'seenBefore' => $seenBefore,
        ])->layout('layouts.app', [
            'title' => 'Neuigkeiten | VEMA',
            'heading' => 'Neuigkeiten',
        ]);
    }
}

<?php

namespace App\Livewire\Federation;

use App\Models\FederationEvent;
use App\Models\FederationFile;
use App\Models\FederationMessage;
use App\Models\Tenant;
use App\Services\TenantManager;
use Livewire\Component;

/**
 * Seite „Verband“ im Verein: Mitteilungen und Dokumente der Verbände,
 * denen der Verein angehört.
 */
class Inbox extends Component
{
    public ?int $openMessageId = null;

    public function mount(): void
    {
        $this->club();
    }

    public function open(int $messageId): void
    {
        if ($this->openMessageId === $messageId) {
            $this->openMessageId = null;

            return;
        }

        $club = $this->club();
        $message = FederationMessage::query()->whereIn('federation_id', $club->activeFederationIds())->findOrFail($messageId);
        $message->markReadBy($club);

        $this->openMessageId = $message->id;
    }

    private function club(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_if($tenant === null, 404);

        return $tenant;
    }

    public function render()
    {
        $club = $this->club();
        $federationIds = $club->activeFederationIds();

        $messages = FederationMessage::query()
            ->whereIn('federation_id', $federationIds)
            ->with(['federation', 'attachments'])
            ->latest('id')
            ->limit(50)
            ->get();

        return view('livewire.federation.inbox', [
            'hasFederations' => $federationIds->isNotEmpty(),
            'messages' => $messages,
            'readIds' => FederationMessage::readIdsFor($club, $messages->pluck('id')),
            'events' => FederationEvent::query()
                ->whereIn('federation_id', $federationIds)
                ->upcoming()
                ->with('federation')
                ->orderBy('starts_at')
                ->limit(10)
                ->get(),
            'documents' => FederationFile::query()
                ->documents()
                ->whereIn('federation_id', $federationIds)
                ->with('federation')
                ->latest('id')
                ->get()
                ->groupBy(fn (FederationFile $file) => $file->federation->name),
        ])
            ->layout('layouts.app', [
                'title' => 'Verband | VEMA',
                'heading' => 'Verband',
            ]);
    }
}

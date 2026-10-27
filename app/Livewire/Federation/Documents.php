<?php

namespace App\Livewire\Federation;

use App\Models\Tenant;
use App\Services\FederationStorage;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Verbandszentrale: Dokumente für alle Mitgliedsvereine (Formulare,
 * Ausschreibungen, Satzung …).
 */
class Documents extends Component
{
    use WithFileUploads;

    public string $title = '';

    public ?UploadedFile $file = null;

    public function mount(): void
    {
        $this->federation();
    }

    public function upload(FederationStorage $storage): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ], [
            'title.required' => 'Bitte einen Titel eingeben.',
            'file.required' => 'Bitte eine Datei auswählen.',
        ]);

        $storage->store($this->federation(), $validated['file'], 'federation/documents', $validated['title']);

        $this->reset(['title', 'file']);

        session()->flash('success', 'Dokument „'.$validated['title'].'“ für die Mitgliedsvereine freigegeben.');
    }

    public function delete(int $fileId): void
    {
        $this->authorizeAdmin();

        $file = $this->federation()->federationFiles()->documents()->findOrFail($fileId);
        $file->delete();

        session()->flash('success', 'Dokument „'.$file->title.'“ gelöscht.');
    }

    private function federation(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_unless($tenant?->isFederation(), 404);

        return $tenant;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function render()
    {
        return view('livewire.federation.documents', [
            'documents' => $this->federation()->federationFiles()->documents()->latest('id')->get(),
            'isAdmin' => (bool) auth()->user()?->hasRole('admin'),
        ])
            ->layout('layouts.app', [
                'title' => 'Dokumente | Verbandszentrale | VEMA',
                'heading' => 'Verbandszentrale',
            ]);
    }
}

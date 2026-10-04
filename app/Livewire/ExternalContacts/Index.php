<?php

namespace App\Livewire\ExternalContacts;

use App\Models\ExternalContact;
use App\Services\ExternalContactImporter;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public ?int $editingContactID = null;

    public bool $showImport = false;

    public ?TemporaryUploadedFile $importFile = null;

    /**
     * Vorschau aus ExternalContactImporter::analyze(). Nur zur Anzeige —
     * beim Import wird die Datei erneut ausgewertet.
     *
     * @var list<array<string, mixed>>
     */
    public array $importPreview = [];

    public string $name = '';

    public string $surname = '';

    public string $organization = '';

    public string $email = '';

    public string $phone = '';

    public string $note = '';

    public bool $active = true;

    public string $search = '';

    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'surname' => [
                'required',
                'string',
                'max:150',
            ],
            'organization' => [
                'nullable',
                'string',
                'max:200',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:100',
            ],
            'note' => [
                'nullable',
                'string',
            ],
            'active' => [
                'boolean',
            ],
        ]);

        if ($this->editingContactID) {
            $contact = ExternalContact::findOrFail(
                $this->editingContactID
            );

            $contact->update([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'organization' => $validated['organization'] ?: null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?: null,
                'note' => $validated['note'] ?: null,
                'active' => $validated['active'],
            ]);

            session()->flash(
                'success',
                'Externer Kontakt wurde aktualisiert.'
            );
        } else {
            ExternalContact::create([
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'organization' => $validated['organization'] ?: null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?: null,
                'note' => $validated['note'] ?: null,
                'active' => $validated['active'],
            ]);

            session()->flash(
                'success',
                'Externer Kontakt wurde angelegt.'
            );
        }

        $this->resetForm();
    }

    public function edit(int $externalContactID): void
    {
        $contact = ExternalContact::findOrFail(
            $externalContactID
        );

        $this->editingContactID = $contact->externalContactID;

        $this->name = $contact->name;
        $this->surname = $contact->surname;
        $this->organization = $contact->organization ?? '';
        $this->email = $contact->email;
        $this->phone = $contact->phone ?? '';
        $this->note = $contact->note ?? '';
        $this->active = (bool) $contact->active;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $externalContactID): void
    {
        $contact = ExternalContact::findOrFail(
            $externalContactID
        );

        $contact->update([
            'active' => ! $contact->active,
        ]);
    }

    public function delete(int $externalContactID): void
    {
        $contact = ExternalContact::findOrFail(
            $externalContactID
        );

        $contact->delete();

        if ($this->editingContactID === $externalContactID) {
            $this->resetForm();
        }

        session()->flash(
            'success',
            'Externer Kontakt wurde gelöscht.'
        );
    }

    public function updatedImportFile(): void
    {
        $this->importPreview = [];

        $this->validateImportFile();

        try {
            $this->importPreview = app(ExternalContactImporter::class)
                ->analyze($this->importFile->getRealPath());
        } catch (InvalidArgumentException $exception) {
            $this->addError('importFile', $exception->getMessage());
        }
    }

    public function runImport(ExternalContactImporter $importer): void
    {
        $this->validateImportFile();

        try {
            $summary = $importer->import(
                $importer->analyze($this->importFile->getRealPath())
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('importFile', $exception->getMessage());

            return;
        }

        $this->cancelImport();

        session()->flash(
            'success',
            "Import abgeschlossen: {$summary['created']} angelegt, {$summary['updated']} aktualisiert, {$summary['skipped']} übersprungen."
        );
    }

    public function cancelImport(): void
    {
        $this->reset(['showImport', 'importFile', 'importPreview']);
        $this->resetValidation('importFile');
    }

    private function validateImportFile(): void
    {
        $this->validate([
            'importFile' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv,txt',
                'max:5120',
            ],
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingContactID',
            'name',
            'surname',
            'organization',
            'email',
            'phone',
            'note',
        ]);

        $this->active = true;
    }

    public function render()
    {
        $contacts = ExternalContact::query()
            ->when(
                trim($this->search) !== '',
                function ($query) {
                    $search = '%'.trim($this->search).'%';

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', $search)
                            ->orWhere('surname', 'like', $search)
                            ->orWhere('organization', 'like', $search)
                            ->orWhere('email', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    });
                }
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.external-contacts.index',
            [
                'contacts' => $contacts,
            ]
        )->layout('layouts.app', [
            'title' => 'Externe Kontakte | VEMA',
            'heading' => 'Externe Kontakte',
        ]);
    }
}

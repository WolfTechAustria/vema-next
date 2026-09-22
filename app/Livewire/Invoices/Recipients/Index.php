<?php

namespace App\Livewire\Invoices\Recipients;

use App\Models\InvoiceRecipient;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingRecipientID = null;

    public string $company_name = '';
    public string $name = '';
    public string $surname = '';
    public string $street = '';
    public string $zip = '';
    public string $city = '';
    public string $country = 'Österreich';
    public string $email = '';
    public string $phone = '';
    public string $vat_id = '';
    public string $note = '';
    public bool $active = true;

    public string $search = '';

    protected function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:200'],
            'name' => ['nullable', 'string', 'max:150'],
            'surname' => ['nullable', 'string', 'max:150'],
            'street' => ['nullable', 'string', 'max:150'],
            'zip' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if (trim($validated['company_name']) === '' && trim($validated['surname']) === '') {
            $this->addError('company_name', 'Bitte entweder einen Firmennamen oder Vor-/Nachnamen angeben.');

            return;
        }

        $data = [
            'company_name' => $validated['company_name'] ?: null,
            'name' => $validated['name'] ?: null,
            'surname' => $validated['surname'] ?: null,
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => $validated['city'] ?: null,
            'country' => $validated['country'] ?: 'Österreich',
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'vat_id' => $validated['vat_id'] ?: null,
            'note' => $validated['note'] ?: null,
            'active' => $validated['active'],
        ];

        if ($this->editingRecipientID) {
            InvoiceRecipient::findOrFail($this->editingRecipientID)->update($data);

            session()->flash('success', 'Rechnungsempfänger wurde aktualisiert.');
        } else {
            InvoiceRecipient::create($data);

            session()->flash('success', 'Rechnungsempfänger wurde angelegt.');
        }

        $this->resetForm();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $recipientID): void
    {
        $recipient = InvoiceRecipient::findOrFail($recipientID);

        $this->showForm = true;
        $this->editingRecipientID = $recipient->recipientID;
        $this->company_name = $recipient->company_name ?? '';
        $this->name = $recipient->name ?? '';
        $this->surname = $recipient->surname ?? '';
        $this->street = $recipient->street ?? '';
        $this->zip = $recipient->zip ?? '';
        $this->city = $recipient->city ?? '';
        $this->country = $recipient->country ?? 'Österreich';
        $this->email = $recipient->email ?? '';
        $this->phone = $recipient->phone ?? '';
        $this->vat_id = $recipient->vat_id ?? '';
        $this->note = $recipient->note ?? '';
        $this->active = (bool) $recipient->active;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $recipientID): void
    {
        $recipient = InvoiceRecipient::findOrFail($recipientID);

        $recipient->update([
            'active' => !$recipient->active,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingRecipientID',
            'company_name',
            'name',
            'surname',
            'street',
            'zip',
            'city',
            'email',
            'phone',
            'vat_id',
            'note',
        ]);

        $this->country = 'Österreich';
        $this->active = true;
    }

    public function render()
    {
        $recipients = InvoiceRecipient::query()
            ->when(
                trim($this->search) !== '',
                function ($query) {
                    $search = '%' . trim($this->search) . '%';

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('company_name', 'like', $search)
                            ->orWhere('name', 'like', $search)
                            ->orWhere('surname', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
                }
            )
            ->orderBy('company_name')
            ->orderBy('surname')
            ->get();

        return view('livewire.invoices.recipients.index', [
            'recipients' => $recipients,
        ])->layout('layouts.app', [
            'title' => 'Rechnungsempfänger | VEMA',
            'heading' => 'Rechnungsempfänger',
        ]);
    }
}

<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCondition;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\InventoryItems;
use App\Services\InventoryPhotoService;
use App\Services\UploadLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Materialstück anlegen bzw. bearbeiten.
 */
class Form extends Component
{
    use WithFileUploads;

    public ?InventoryItem $item = null;

    public string $name = '';

    public string $category = '';

    public string $inventoryNumber = '';

    public string $code = '';

    public string $location = '';

    public string $condition = 'good';

    public string $purchasedOn = '';

    public string $purchasePrice = '';

    public string $note = '';

    public string $inspectionIntervalMonths = '';

    public string $nextInspectionOn = '';

    public ?UploadedFile $photo = null;

    public function mount(?InventoryItem $item = null): void
    {
        if ($item === null || ! $item->exists) {
            $this->item = null;
            $this->code = mb_substr(trim((string) request()->query('code', '')), 0, 100);

            return;
        }

        $this->item = $item;
        $this->name = $item->name;
        $this->category = $item->category?->name ?? '';
        $this->inventoryNumber = $item->inventory_number ?? '';
        $this->code = $item->code ?? '';
        $this->location = $item->location ?? '';
        $this->condition = $item->condition->value;
        $this->purchasedOn = $item->purchased_on?->toDateString() ?? '';
        $this->purchasePrice = $item->purchase_price !== null ? str_replace('.', ',', (string) $item->purchase_price) : '';
        $this->note = $item->note ?? '';
        $this->inspectionIntervalMonths = (string) ($item->inspection_interval_months ?? '');
        $this->nextInspectionOn = $item->next_inspection_on?->toDateString() ?? '';
    }

    public function save(InventoryItems $items, InventoryPhotoService $photos): void
    {
        $this->purchasePrice = str_replace(',', '.', trim($this->purchasePrice));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'inventoryNumber' => ['nullable', 'string', 'max:50', Rule::unique('tb_inventory_items', 'inventory_number')->ignore($this->item?->itemID, 'itemID')],
            'code' => ['nullable', 'string', 'max:100', Rule::unique('tb_inventory_items', 'code')->ignore($this->item?->itemID, 'itemID')],
            'location' => ['nullable', 'string', 'max:150'],
            'condition' => ['required', Rule::enum(InventoryCondition::class)],
            'purchasedOn' => ['nullable', 'date'],
            'purchasePrice' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:5000'],
            'inspectionIntervalMonths' => ['nullable', 'integer', 'min:1', 'max:240'],
            'nextInspectionOn' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.InventoryPhotoService::MAX_SIZE_KB],
        ], [], [
            'name' => 'Bezeichnung',
            'category' => 'Kategorie',
            'inventoryNumber' => 'Inventarnummer',
            'code' => 'Barcode/NFC',
            'location' => 'Lagerort',
            'condition' => 'Zustand',
            'purchasedOn' => 'Anschaffung',
            'purchasePrice' => 'Anschaffungspreis',
            'note' => 'Notiz',
            'inspectionIntervalMonths' => 'Prüfintervall',
            'nextInspectionOn' => 'Nächste Prüfung',
            'photo' => 'Foto',
        ]);

        $isNew = $this->item === null;

        $item = $items->save($this->item, [
            'name' => $validated['name'],
            'category' => $validated['category'],
            'inventory_number' => $validated['inventoryNumber'],
            'code' => $validated['code'],
            'location' => $validated['location'],
            'condition' => $validated['condition'],
            'purchased_on' => $validated['purchasedOn'],
            'purchase_price' => $validated['purchasePrice'],
            'note' => $validated['note'],
            'inspection_interval_months' => $validated['inspectionIntervalMonths'],
            'next_inspection_on' => $validated['nextInspectionOn'],
        ]);

        if ($this->photo !== null) {
            try {
                $photos->store($item, $this->photo);
            } catch (RuntimeException $exception) {
                session()->flash('error', $exception->getMessage());
            }

            $this->photo = null;
        }

        session()->flash('success', $isNew ? 'Material wurde angelegt.' : 'Material wurde gespeichert.');

        $this->redirectRoute('inventory.show', ['item' => $item], navigate: true);
    }

    public function removePhoto(InventoryPhotoService $photos): void
    {
        abort_if($this->item === null, 404);

        $photos->remove($this->item);
    }

    public function render()
    {
        return view('livewire.inventory.form', [
            'categories' => InventoryCategory::query()->orderBy('name')->pluck('name'),
            'locations' => InventoryItem::query()->whereNotNull('location')->distinct()->orderBy('location')->pluck('location'),
            'conditions' => InventoryCondition::cases(),
            'photoUrl' => $this->item ? app(InventoryPhotoService::class)->url($this->item) : null,
            'photoMaxKb' => app(UploadLimits::class)->effectiveKb(InventoryPhotoService::MAX_SIZE_KB),
        ])->layout('layouts.app', [
            'title' => ($this->item ? 'Material bearbeiten' : 'Neues Material').' | VEMA',
            'heading' => 'Material',
        ]);
    }
}

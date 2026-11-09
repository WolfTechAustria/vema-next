<?php

namespace App\Services;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Materialstücke anlegen, ändern und löschen. Die Kategorie wird frei
 * eingetippt; neue Kategorien landen in der Vorschlagsliste, nicht mehr
 * verwendete verschwinden wieder daraus.
 */
class InventoryItems
{
    /**
     * @param  array{name: string, category: ?string, inventory_number: ?string, code?: ?string, location: ?string, condition: string, purchased_on: ?string, purchase_price: ?string, note: ?string, inspection_interval_months: int|string|null, next_inspection_on: ?string}  $data
     */
    public function save(?InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data) {
            $attributes = [
                'name' => trim($data['name']),
                'categoryID' => filled($data['category']) ? $this->categoryFor($data['category'])->categoryID : null,
                'inventory_number' => $this->nullable($data['inventory_number']),
                'code' => $this->nullable($data['code'] ?? null),
                'location' => $this->nullable($data['location']),
                'condition' => $data['condition'],
                'purchased_on' => $data['purchased_on'] ?: null,
                'purchase_price' => filled($data['purchase_price']) ? str_replace(',', '.', (string) $data['purchase_price']) : null,
                'note' => $this->nullable($data['note']),
                'inspection_interval_months' => filled($data['inspection_interval_months']) ? (int) $data['inspection_interval_months'] : null,
                'next_inspection_on' => $data['next_inspection_on'] ?: null,
            ];

            if ($item === null) {
                $item = InventoryItem::create($attributes);

                ActivityLogger::log(
                    'inventory.created',
                    'Material "'.$item->label().'" wurde angelegt.',
                    'InventoryItem',
                    $item->itemID,
                    ActivityLogger::created($item->fresh()->activitySnapshot(), InventoryItem::ACTIVITY_LABELS)
                );

                return $item;
            }

            $before = $item->activitySnapshot();
            $previousCategory = $item->category;

            $item->update($attributes);
            $item->load('category');

            if ($previousCategory !== null) {
                $this->forgetIfUnused($previousCategory);
            }

            $changes = ActivityLogger::changes($before, $item->activitySnapshot(), InventoryItem::ACTIVITY_LABELS);

            if ($changes !== []) {
                ActivityLogger::log(
                    'inventory.updated',
                    'Material "'.$item->label().'" wurde geändert.',
                    'InventoryItem',
                    $item->itemID,
                    $changes
                );
            }

            return $item;
        });
    }

    public function delete(InventoryItem $item): void
    {
        DB::transaction(function () use ($item) {
            $snapshot = $item->activitySnapshot();
            $category = $item->category;
            $photoPath = $item->photo_path;

            $item->loans()->delete();
            $item->inspections()->delete();
            $item->delete();

            if ($category !== null) {
                $this->forgetIfUnused($category);
            }

            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            ActivityLogger::log(
                'inventory.deleted',
                'Material "'.$item->label().'" wurde gelöscht.',
                'InventoryItem',
                $item->itemID,
                ActivityLogger::changes($snapshot, [], InventoryItem::ACTIVITY_LABELS)
            );
        });
    }

    /**
     * Vorhandene Kategorie (ohne Rücksicht auf Groß-/Kleinschreibung) oder neue.
     */
    private function categoryFor(string $name): InventoryCategory
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return InventoryCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? InventoryCategory::create(['name' => $name]);
    }

    private function forgetIfUnused(InventoryCategory $category): void
    {
        if (! $category->items()->exists()) {
            $category->delete();
        }
    }

    private function nullable(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }
}

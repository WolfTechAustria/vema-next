<?php

namespace App\Rules;

use App\Enums\Limit;
use App\Services\StorageUsage;
use App\Services\TenantManager;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Upload passt noch in den Speicher des Pakets (gilt für alle
 * Livewire-Uploads, nur Plattform-Betrieb).
 */
class WithinStorageQuota implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tenant = app(TenantManager::class)->current();
        $limitMb = $tenant?->limit(Limit::StorageMb);

        if ($limitMb === null || ! $value instanceof UploadedFile) {
            return;
        }

        $storageUsage = app(StorageUsage::class);
        $usedBytes = $storageUsage->currentUsedBytes();
        $size = (int) $value->getSize();

        if ($usedBytes + $size > $limitMb * 1024 * 1024) {
            $fail('Der Speicherplatz eures Pakets ist voll ('.Limit::formatMegabytes($usedBytes / 1024 / 1024)
                .' von '.Limit::StorageMb->format($limitMb).' belegt). Bitte nicht mehr benötigte Dateien löschen oder mehr Speicher buchen.');

            return;
        }

        $storageUsage->addToCurrent($size);
    }
}

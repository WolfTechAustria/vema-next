<?php

namespace App\Services;

use App\Models\Tenant;
use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Belegter Speicher eines Vereins (Dateien und Branding, ohne Testmodus).
 */
class StorageUsage
{
    private const CACHE_KEY = 'storage-usage-bytes';

    private const CACHE_SECONDS = 600;

    public function __construct(private TenantManager $tenantManager) {}

    public function usedBytes(Tenant $tenant): int
    {
        return $this->directorySize($tenant->storagePath('private'))
            + $this->directorySize($tenant->storagePath('branding'));
    }

    /**
     * Belegung des aktuellen Vereins, einige Minuten zwischengespeichert.
     */
    public function currentUsedBytes(): int
    {
        $tenant = $this->tenantManager->current();

        if ($tenant === null) {
            return 0;
        }

        return (int) Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->usedBytes($tenant));
    }

    /**
     * Neuen Upload sofort mitzählen (bis der Zwischenspeicher abläuft).
     */
    public function addToCurrent(int $bytes): void
    {
        if ($this->tenantManager->current() !== null && Cache::has(self::CACHE_KEY)) {
            Cache::increment(self::CACHE_KEY, $bytes);
        }
    }

    private function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }
}

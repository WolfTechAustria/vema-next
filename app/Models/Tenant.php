<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Verein auf der Plattform (nur TENANCY_MODE=multi). Liegt als eines von
 * wenigen Models fest in der zentralen Datenbank.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $connection = 'landlord';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'license_valid_until' => 'date',
        ];
    }

    /**
     * @return HasMany<TenantDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Freigeschaltet und Lizenz nicht abgelaufen (Ablaufdatum inklusive).
     */
    public function isAccessible(): bool
    {
        if ($this->status !== TenantStatus::Active) {
            return false;
        }

        return $this->license_valid_until === null
            || $this->license_valid_until->endOfDay()->isFuture();
    }

    /**
     * Subdomain auf der Plattform, z. B. "sg-musterdorf.vemat.at".
     */
    public function host(): string
    {
        return $this->slug.'.'.config('tenancy.central_domain');
    }

    public function url(): string
    {
        return config('tenancy.scheme').'://'.$this->host();
    }

    /**
     * Wurzelordner der Dateien dieses Vereins.
     */
    public function storagePath(string $path = ''): string
    {
        return rtrim(config('tenancy.storage_root'), '/\\')
            .DIRECTORY_SEPARATOR.$this->getKey()
            .($path !== '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Datenbank für den Testmodus dieses Vereins.
     */
    public function demoDatabase(): string
    {
        return $this->database.'_demo';
    }
}

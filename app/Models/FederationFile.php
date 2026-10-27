<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;

/**
 * Dokument eines Verbands oder Anhang einer Mitteilung (zentrale Datenbank).
 * Die Datei liegt im privaten Speicher des Verbands.
 */
class FederationFile extends Model
{
    protected $connection = 'landlord';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleted(function (FederationFile $file) {
            File::delete($file->absolutePath());
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'federation_id');
    }

    /**
     * Freigegebene Dokumente (keine Anhänge von Mitteilungen).
     *
     * @param  Builder<self>  $query
     */
    public function scopeDocuments(Builder $query): void
    {
        $query->whereNull('federation_message_id');
    }

    /**
     * Pfad im Dateisystem — unabhängig davon, welcher Verein gerade aktiv ist.
     */
    public function absolutePath(): string
    {
        return $this->federation->storagePath('private'.DIRECTORY_SEPARATOR.$this->file_path);
    }

    /**
     * Darf der Mandant die Datei öffnen? Der Verband selbst und seine aktiven Mitgliedsvereine.
     */
    public function isAccessibleFor(?Tenant $tenant): bool
    {
        if ($tenant === null) {
            return false;
        }

        return $tenant->is($this->federation) || $tenant->isMemberOf($this->federation);
    }
}

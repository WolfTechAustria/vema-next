<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mitteilung eines Verbands an alle Mitgliedsvereine (zentrale Datenbank).
 */
class FederationMessage extends Model
{
    protected $connection = 'landlord';

    protected $guarded = [];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'federation_id');
    }

    /**
     * @return HasMany<FederationFile, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(FederationFile::class);
    }

    /**
     * Wie viele Vereine die Mitteilung geöffnet haben.
     */
    public function readCount(): int
    {
        return DB::connection($this->getConnectionName())
            ->table('federation_message_reads')
            ->where('federation_message_id', $this->id)
            ->count();
    }

    /**
     * Welche der Mitteilungen der Verein schon geöffnet hat.
     *
     * @param  Collection<int, int>  $messageIds
     * @return list<int>
     */
    public static function readIdsFor(Tenant $club, Collection $messageIds): array
    {
        return DB::connection((new static)->getConnectionName())
            ->table('federation_message_reads')
            ->where('club_id', $club->id)
            ->whereIn('federation_message_id', $messageIds)
            ->pluck('federation_message_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function markReadBy(Tenant $club): void
    {
        DB::connection($this->getConnectionName())
            ->table('federation_message_reads')
            ->insertOrIgnore([
                'federation_message_id' => $this->id,
                'club_id' => $club->id,
                'read_at' => now(),
            ]);
    }
}

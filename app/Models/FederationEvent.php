<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Für die Mitgliedsvereine freigegebener Termin eines Verbands (zentrale
 * Datenbank). Wird bei jeder Änderung aus tb_events des Verbands neu erstellt.
 */
class FederationEvent extends Model
{
    protected $connection = 'landlord';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'federation_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere('ends_at', '>=', now()->startOfDay()));
    }
}

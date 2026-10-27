<?php

namespace App\Models;

use App\Enums\FederationMembershipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mitgliedschaft eines Vereins in einem Verband (zentrale Datenbank).
 */
class FederationMembership extends Model
{
    protected $connection = 'landlord';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FederationMembershipStatus::class,
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @return BelongsTo<Tenant, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'club_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', FederationMembershipStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInvited(Builder $query): void
    {
        $query->where('status', FederationMembershipStatus::Invited);
    }
}

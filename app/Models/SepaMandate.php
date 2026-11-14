<?php

namespace App\Models;

use Database\Factories\SepaMandateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SEPA-Lastschriftmandat eines Mitglieds. Aktiv ist ein Mandat, solange es
 * nicht widerrufen wurde; die IBAN wird verschlüsselt gespeichert.
 */
class SepaMandate extends Model
{
    /** @use HasFactory<SepaMandateFactory> */
    use HasFactory;

    public const SOURCE_PORTAL = 'portal';

    public const SOURCE_MANUAL = 'manual';

    protected $table = 'tb_member_sepa_mandates';

    protected $primaryKey = 'mandateID';

    protected $guarded = [];

    protected $hidden = ['iban'];

    protected function casts(): array
    {
        return [
            'iban' => 'encrypted',
            'signed_on' => 'date',
            'first_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoked_by_member' => 'boolean',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * „AT61 **** **** **** 1234“
     */
    public function maskedIban(): string
    {
        $iban = (string) $this->iban;

        return substr($iban, 0, 4).' **** **** **** '.substr($iban, -4);
    }

    /**
     * Gruppiert in Viererblöcken: „AT61 1904 3002 3457 3201“.
     */
    public function formattedIban(): string
    {
        return trim(chunk_split((string) $this->iban, 4, ' '));
    }

    /**
     * Erste Lastschrift auf dieses Mandat (FRST) oder Folgelastschrift (RCUR).
     */
    public function sequenceType(): string
    {
        return $this->first_used_at === null ? 'FRST' : 'RCUR';
    }
}

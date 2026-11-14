<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Lastschrift-Lauf: eine SEPA-XML-Datei (pain.008) über mehrere
 * Mitgliedsbeiträge. Erst nach der Bestätigung gelten sie als bezahlt.
 */
class SepaDebitRun extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'tb_sepa_debit_runs';

    protected $primaryKey = 'runID';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'total_amount' => 'decimal:2',
            'entry_count' => 'integer',
            'confirmed_at' => 'datetime',
        ];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(MembershipFeeYear::class, 'yearID', 'yearID');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SepaDebitItem::class, 'runID', 'runID');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CREATED => 'Eingereicht – wartet auf Bestätigung',
            self::STATUS_CONFIRMED => 'Bestätigt',
            self::STATUS_CANCELLED => 'Verworfen',
            default => $this->status,
        };
    }

    public function statusClasses(): string
    {
        return match ($this->status) {
            self::STATUS_CREATED => 'bg-amber-100 text-amber-800',
            self::STATUS_CONFIRMED => 'bg-emerald-100 text-emerald-800',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}

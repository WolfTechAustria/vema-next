<?php

namespace App\Models;

use App\Enums\CashBookEntryType;
use Database\Factories\CashBookEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashBookEntry extends Model
{
    /** @use HasFactory<CashBookEntryFactory> */
    use HasFactory;

    protected $table = 'tb_cash_book_entries';

    protected $primaryKey = 'cashBookEntryID';

    protected $guarded = [];

    protected $casts = [
        'cashBookYearID' => 'integer',
        'receipt_number' => 'integer',
        'date' => 'date',
        'type' => CashBookEntryType::class,
        'amount' => 'decimal:2',
        'created_by' => 'integer',
    ];

    /**
     * @return BelongsTo<CashBookYear, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(
            CashBookYear::class,
            'cashBookYearID',
            'cashBookYearID'
        );
    }

    /**
     * @return HasMany<CashBookAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(
            CashBookAttachment::class,
            'cashBookEntryID',
            'cashBookEntryID'
        );
    }

    public function isIncome(): bool
    {
        return $this->type === CashBookEntryType::Income;
    }

    /**
     * Betrag mit Vorzeichen (Ausgaben negativ) für Saldo-Berechnungen.
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isIncome()
            ? (float) $this->amount
            : -(float) $this->amount;
    }
}

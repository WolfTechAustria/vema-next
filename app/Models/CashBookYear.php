<?php

namespace App\Models;

use App\Enums\CashBookEntryType;
use Carbon\CarbonInterface;
use Database\Factories\CashBookYearFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vereinsjahr des Kassabuchs (von Jahreshauptversammlung zu
 * Jahreshauptversammlung). Nach dem Abschluss sind keine Buchungen mehr
 * änderbar; der Endbestand wird Anfangsbestand des Folgejahres.
 */
class CashBookYear extends Model
{
    /** @use HasFactory<CashBookYearFactory> */
    use HasFactory;

    protected $table = 'tb_cash_book_years';

    protected $primaryKey = 'cashBookYearID';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'general_meeting_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'closed_at' => 'datetime',
        'closed_by' => 'integer',
    ];

    /**
     * @return HasMany<CashBookEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(
            CashBookEntry::class,
            'cashBookYearID',
            'cashBookYearID'
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'id');
    }

    /**
     * @param  Builder<CashBookYear>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function containsDate(CarbonInterface $date): bool
    {
        return $date->betweenIncluded(
            $this->start_date->copy()->startOfDay(),
            $this->end_date->copy()->endOfDay()
        );
    }

    /**
     * Offenes Vereinsjahr, in dem das heutige Datum liegt — sonst das
     * jüngste offene, sonst das jüngste überhaupt.
     */
    public static function current(): ?self
    {
        $today = today()->toDateString();

        return self::open()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first()
            ?? self::open()->orderByDesc('start_date')->first()
            ?? self::orderByDesc('start_date')->first();
    }

    /**
     * Summen der Einnahmen und Ausgaben in einer einzigen Abfrage.
     *
     * @return array{income: float, expense: float}
     */
    public function totals(): array
    {
        $sums = $this->entries()
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'income' => round((float) ($sums[CashBookEntryType::Income->value] ?? 0), 2),
            'expense' => round((float) ($sums[CashBookEntryType::Expense->value] ?? 0), 2),
        ];
    }

    public function balance(): float
    {
        $totals = $this->totals();

        return round(
            (float) $this->opening_balance + $totals['income'] - $totals['expense'],
            2
        );
    }

    public function nextReceiptNumber(): int
    {
        return ((int) $this->entries()->max('receipt_number')) + 1;
    }
}

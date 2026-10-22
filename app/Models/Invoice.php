<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'tb_invoices';

    protected $primaryKey = 'invoiceID';

    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'small_business_no_vat' => 'boolean',
        'subtotal_net' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(InvoiceRecipient::class, 'recipientID', 'recipientID');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoiceID', 'invoiceID')
            ->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(InvoiceReminder::class, 'invoiceID', 'invoiceID')
            ->orderBy('level')
            ->orderBy('sent_at');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query
            ->where('status', 'sent')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent'
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    public function lastReminderLevel(): int
    {
        return (int) $this->reminders->max('level');
    }

    /**
     * Nächste Stufe (1–3) oder null, wenn alle Stufen versendet wurden.
     */
    public function nextReminderLevel(): ?int
    {
        $next = $this->lastReminderLevel() + 1;

        return $next <= InvoiceReminder::MAX_LEVEL ? $next : null;
    }

    /**
     * Ab wann die nächste Stufe fällig ist: erste Stufe = Fälligkeit + Frist,
     * danach letzter Versand + Abstand (Vereinseinstellungen).
     */
    public function nextReminderDate(): ?CarbonInterface
    {
        if ($this->status !== 'sent' || $this->due_date === null || $this->nextReminderLevel() === null) {
            return null;
        }

        $settings = Setting::current();
        $lastReminder = $this->reminders->sortByDesc('sent_at')->first();

        if (! $lastReminder) {
            return $this->due_date->copy()->addDays((int) $settings->invoice_reminder_first_after_days);
        }

        return $lastReminder->sent_at->copy()->startOfDay()->addDays((int) $settings->invoice_reminder_interval_days);
    }

    public function isReminderDue(): bool
    {
        $date = $this->nextReminderDate();

        return $date !== null && $date->lte(today());
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }
}

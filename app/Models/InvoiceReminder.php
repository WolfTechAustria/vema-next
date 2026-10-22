<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminder extends Model
{
    /**
     * @var array<int, string>
     */
    public const LEVELS = [
        1 => 'Zahlungserinnerung',
        2 => '1. Mahnung',
        3 => '2. Mahnung',
    ];

    public const MAX_LEVEL = 3;

    protected $table = 'tb_invoice_reminders';

    protected $primaryKey = 'reminderID';

    protected $guarded = [];

    protected $casts = [
        'level' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoiceID', 'invoiceID');
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? 'Mahnung';
    }

    public function isPrinted(): bool
    {
        return $this->delivery_method === 'post';
    }
}

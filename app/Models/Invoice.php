<?php

namespace App\Models;

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

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }
}

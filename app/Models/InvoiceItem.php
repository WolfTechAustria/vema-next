<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'tb_invoice_items';

    protected $primaryKey = 'invoiceItemID';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price_net' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total_net' => 'decimal:2',
        'line_total_gross' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoiceID', 'invoiceID');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'articleID', 'articleID');
    }
}

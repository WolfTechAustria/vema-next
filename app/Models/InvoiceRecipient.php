<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceRecipient extends Model
{
    protected $table = 'tb_invoice_recipients';

    protected $primaryKey = 'recipientID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim($this->surname . ' ' . $this->name);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recipientID', 'recipientID');
    }
}

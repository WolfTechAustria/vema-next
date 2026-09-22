<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberSequence extends Model
{
    protected $table = 'tb_invoice_number_sequences';

    protected $primaryKey = 'year';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}

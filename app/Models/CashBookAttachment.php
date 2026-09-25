<?php

namespace App\Models;

use Database\Factories\CashBookAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBookAttachment extends Model
{
    /** @use HasFactory<CashBookAttachmentFactory> */
    use HasFactory;

    protected $table = 'tb_cash_book_attachments';

    protected $primaryKey = 'attachmentID';

    protected $guarded = [];

    protected $casts = [
        'cashBookEntryID' => 'integer',
        'file_size' => 'integer',
    ];

    /**
     * @return BelongsTo<CashBookEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(
            CashBookEntry::class,
            'cashBookEntryID',
            'cashBookEntryID'
        );
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}

<?php

namespace App\Models;

use Database\Factories\PostAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PDF-Download zu einem Beitrag (z. B. Ergebnisliste, Einladung).
 */
class PostAttachment extends Model
{
    /** @use HasFactory<PostAttachmentFactory> */
    use HasFactory;

    protected $table = 'tb_post_attachments';

    protected $primaryKey = 'postAttachmentID';

    protected $guarded = [];

    protected $casts = [
        'file_size' => 'integer',
        'sort' => 'integer',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'postID', 'postID');
    }
}

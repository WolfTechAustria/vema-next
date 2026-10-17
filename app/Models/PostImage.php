<?php

namespace App\Models;

use Database\Factories\PostImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bild der Galerie eines Beitrags (Großbild + Vorschaubild, Disk „local“).
 */
class PostImage extends Model
{
    /** @use HasFactory<PostImageFactory> */
    use HasFactory;

    protected $table = 'tb_post_images';

    protected $primaryKey = 'postImageID';

    protected $guarded = [];

    protected $casts = [
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

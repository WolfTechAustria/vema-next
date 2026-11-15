<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Neuigkeit zu VEMA in einer eigenen Installation (z. B. Angerberg), vom
 * Feed der Plattform übernommen (siehe PlatformNewsPull).
 */
class VemaNews extends Model
{
    protected $table = 'tb_vema_news';

    protected $primaryKey = 'newsID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remote_id' => 'integer',
            'published_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    /**
     * Text als HTML (Markdown, eigenes HTML wird nicht übernommen).
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]));
    }
}

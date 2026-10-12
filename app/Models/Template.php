<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    public const MEMBER_WELCOME = 'member_welcome';

    public const STAFF_WELCOME = 'staff_welcome';

    /**
     * Vorlagen, die nur als E-Mail-Text dienen — keine Briefvorlage, also
     * weder Ausgangspunkt für Rundschreiben noch PDF-Vorschau.
     *
     * @var list<string>
     */
    public const MAIL_ONLY_KEYS = [
        self::MEMBER_WELCOME,
        self::STAFF_WELCOME,
    ];

    protected $table = 'tb_templates';

    protected $primaryKey = 'templateID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function isMailOnly(): bool
    {
        return in_array($this->key, self::MAIL_ONLY_KEYS, true);
    }

    /**
     * Briefvorlagen (Beitragsschreiben, Rundschreiben).
     */
    public function scopeLetters(Builder $query): Builder
    {
        return $query->whereNotIn('key', self::MAIL_ONLY_KEYS);
    }
}

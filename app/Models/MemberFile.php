<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Hochgeladenes Dokument zu einem Mitglied, gespeichert auf der Disk
 * „local“ (je Verein und im Testmodus getrennt).
 */
class MemberFile extends Model
{
    /**
     * Erlaubte Dateitypen beim Hochladen.
     *
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt'];

    /**
     * Maximale Dateigröße in KB (10 MB).
     */
    public const MAX_SIZE_KB = 10240;

    /**
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'name' => 'Name',
        'category' => 'Kategorie',
        'note' => 'Notiz',
        'visible_to_member' => 'Für das Mitglied sichtbar',
    ];

    protected $table = 'tb_member_files';

    protected $primaryKey = 'fileID';

    protected $guarded = [];

    protected $casts = [
        'file_size' => 'integer',
        'visible_to_member' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }

    public function scopeVisibleToMember(Builder $query): Builder
    {
        return $query->where('visible_to_member', true);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/') && $this->mime_type !== 'image/heic';
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function deleteWithFile(): void
    {
        Storage::disk('local')->delete($this->file_path);

        $this->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'note' => $this->note,
            'visible_to_member' => (bool) $this->visible_to_member,
        ];
    }
}

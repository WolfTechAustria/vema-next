<?php

namespace App\Models;

use Database\Factories\HomepageRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Weiterleitung einer alten Adresse der Homepage (301), z. B. von der
 * früheren Vereinsseite. Endet die Quelle auf „*“, gilt sie für alle
 * Adressen, die so beginnen.
 */
class HomepageRedirect extends Model
{
    /** @use HasFactory<HomepageRedirectFactory> */
    use HasFactory;

    protected $table = 'tb_homepage_redirects';

    protected $primaryKey = 'redirectID';

    protected $guarded = [];

    protected $casts = [
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function isPrefix(): bool
    {
        return str_ends_with($this->source_path, '*');
    }

    /**
     * Weiterleitung nach einer Adressänderung merken. Bestehende
     * Weiterleitungen auf die alte Adresse zeigen gleich auf die neue, und
     * eine Weiterleitung von der neuen Adresse entfällt (sie gibt es wieder).
     */
    public static function remember(string $sourcePath, string $target): void
    {
        $sourcePath = static::normalizePath($sourcePath);
        $target = static::normalizePath($target);

        static::query()->where('source_path', $target)->delete();
        static::query()->where('target', $sourcePath)->update(['target' => $target]);
        static::query()->updateOrCreate(['source_path' => $sourcePath], ['target' => $target]);
    }

    /**
     * Einheitliche Schreibweise: führender Schrägstrich, ohne Domain, Abfrage
     * und abschließenden Schrägstrich, in Kleinbuchstaben.
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (preg_match('#^https?://#i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }

        $path = Str::before(Str::before($path, '?'), '#');
        $path = Str::lower(rawurldecode($path));
        $path = '/'.trim($path, '/');

        return Str::limit($path, 255, '');
    }
}

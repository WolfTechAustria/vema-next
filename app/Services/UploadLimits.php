<?php

namespace App\Services;

/**
 * Wirksame Upload-Grenzen: Neben den Grenzen von VEMA gelten die des
 * Servers (php.ini: upload_max_filesize, post_max_size). Größere Dateien
 * verwirft PHP, bevor VEMA sie prüfen kann.
 */
class UploadLimits
{
    /**
     * Größter Upload, den die temporären Livewire-Uploads annehmen (25 MB).
     */
    public const TEMPORARY_UPLOAD_MAX_KB = 25600;

    /**
     * Server-Grenze in KB; null = unbegrenzt.
     */
    public function serverMaxKb(): ?int
    {
        $limits = array_filter([
            $this->toKb((string) ini_get('upload_max_filesize')),
            $this->toKb((string) ini_get('post_max_size')),
        ], fn (?int $limit) => $limit !== null && $limit > 0);

        return $limits === [] ? null : min($limits);
    }

    /**
     * Kleinere der beiden Grenzen (VEMA und Server) in KB.
     */
    public function effectiveKb(int $appMaxKb): int
    {
        $serverMaxKb = $this->serverMaxKb();

        return $serverMaxKb === null ? $appMaxKb : min($appMaxKb, $serverMaxKb);
    }

    public static function label(int $kilobytes): string
    {
        $megabytes = $kilobytes / 1024;

        return ($megabytes >= 1 ? rtrim(rtrim(number_format($megabytes, 1, ',', ''), '0'), ',') : '1').' MB';
    }

    private function toKb(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1' || $value === '0') {
            return null;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        };
    }
}

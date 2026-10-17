<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PDF-Dateien der Vereinshomepage (Downloads zu Beiträgen, Ankündigung,
 * Info-Box). Gespeichert auf der Disk „local“ unter zufälligem Namen.
 */
class HomepageFileService
{
    public const MAX_SIZE_KB = 20480;

    public function storePdf(UploadedFile $upload, string $directory): string
    {
        $handle = fopen($upload->getRealPath(), 'rb');
        $header = $handle ? (string) fread($handle, 5) : '';

        if ($handle) {
            fclose($handle);
        }

        if ($header !== '%PDF-') {
            throw new RuntimeException('Die Datei ist kein gültiges PDF.');
        }

        $path = trim($directory, '/').'/'.Str::random(20).'.pdf';

        Storage::disk('local')->putFileAs(dirname($path), $upload, basename($path));

        return $path;
    }

    public function delete(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}

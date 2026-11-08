<?php

namespace App\Services;

use App\Models\InventoryItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fotos der Materialstücke. Das Bild wird verkleinert und als JPEG neu
 * gespeichert — das entfernt Metadaten und stellt sicher, dass es ein Bild ist.
 */
class InventoryPhotoService
{
    public const MAX_EDGE = 1200;

    public const MAX_SIZE_KB = 10240;

    public function store(InventoryItem $item, UploadedFile $upload): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $photo = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled($photo, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($photo, null, 85);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($photo);

        $path = 'inventory/'.$item->itemID.'/'.Str::random(20).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $previousPath = $item->photo_path;

        $item->update(['photo_path' => $path]);

        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    public function remove(InventoryItem $item): void
    {
        if ($item->photo_path) {
            Storage::disk('local')->delete($item->photo_path);
        }

        $item->update(['photo_path' => null]);
    }

    /**
     * Versionierte Adresse des Fotos; null ohne Foto.
     */
    public function url(InventoryItem $item, string $context = 'staff'): ?string
    {
        if (! $item->photo_path) {
            return null;
        }

        $version = substr(md5($item->photo_path), 0, 10);

        return $context === 'portal'
            ? route('member.inventory.photo', ['item' => $item, 'v' => $version])
            : route('inventory.photo', ['item' => $item, 'v' => $version]);
    }
}

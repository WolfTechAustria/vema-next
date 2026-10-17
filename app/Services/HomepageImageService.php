<?php

namespace App\Services;

use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\PostImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bilder der Vereinshomepage (Beitragsgalerien, Titelbild). Jedes Bild wird
 * verkleinert und als JPEG neu gespeichert — das entfernt Metadaten
 * (z. B. GPS) und stellt sicher, dass wirklich ein Bild ausgeliefert wird.
 */
class HomepageImageService
{
    public const MAX_SIZE_KB = 10240;

    private const LARGE_EDGE = 1600;

    private const HERO_EDGE = 2000;

    private const THUMB_WIDTH = 640;

    private const THUMB_HEIGHT = 480;

    public function storePostImage(Post $post, UploadedFile $upload, ?string $caption = null): PostImage
    {
        $source = $this->read($upload);
        $directory = 'homepage/posts/'.$post->postID.'/';
        $name = Str::random(20);

        $large = $directory.$name.'.jpg';
        $thumb = $directory.$name.'_thumb.jpg';

        Storage::disk('local')->put($large, $this->encode($this->fit($source, self::LARGE_EDGE)));
        Storage::disk('local')->put($thumb, $this->encode($this->cover($source, self::THUMB_WIDTH, self::THUMB_HEIGHT)));

        imagedestroy($source);

        return $post->images()->create([
            'file_path' => $large,
            'thumb_path' => $thumb,
            'caption' => filled($caption) ? $caption : null,
            'sort' => (int) $post->images()->max('sort') + 1,
        ]);
    }

    public function deletePostImage(PostImage $image): void
    {
        Storage::disk('local')->delete([$image->file_path, $image->thumb_path]);

        $image->delete();
    }

    /**
     * Speichert ein verkleinertes JPEG im Ordner und liefert den Pfad.
     */
    public function storeImage(UploadedFile $upload, string $directory, int $maxEdge = self::LARGE_EDGE): string
    {
        $source = $this->read($upload);
        $path = trim($directory, '/').'/'.Str::random(20).'.jpg';

        Storage::disk('local')->put($path, $this->encode($this->fit($source, $maxEdge)));

        imagedestroy($source);

        return $path;
    }

    public function storeHeroImage(HomepageSetting $settings, UploadedFile $upload): void
    {
        $path = $this->storeImage($upload, 'homepage/hero', self::HERO_EDGE);

        $previousPath = $settings->hero_image_path;

        $settings->update(['hero_image_path' => $path]);

        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    public function removeHeroImage(HomepageSetting $settings): void
    {
        if ($settings->hero_image_path) {
            Storage::disk('local')->delete($settings->hero_image_path);
        }

        $settings->update(['hero_image_path' => null]);
    }

    private function read(UploadedFile $upload): GdImage
    {
        $source = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }

        return $source;
    }

    /**
     * Verkleinert proportional auf die maximale Kantenlänge (nie vergrößern).
     */
    private function fit(GdImage $source, int $maxEdge): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxEdge / max($width, $height));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        return $this->resample($source, 0, 0, $width, $height, $targetWidth, $targetHeight);
    }

    /**
     * Füllt das Zielformat vollständig und schneidet mittig zu.
     */
    private function cover(GdImage $source, int $targetWidth, int $targetHeight): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = max($targetWidth / $width, $targetHeight / $height);

        $cropWidth = (int) round($targetWidth / $scale);
        $cropHeight = (int) round($targetHeight / $scale);

        return $this->resample(
            $source,
            intdiv($width - $cropWidth, 2),
            intdiv($height - $cropHeight, 2),
            $cropWidth,
            $cropHeight,
            $targetWidth,
            $targetHeight
        );
    }

    private function resample(GdImage $source, int $x, int $y, int $width, int $height, int $targetWidth, int $targetHeight): GdImage
    {
        $image = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, $x, $y, $targetWidth, $targetHeight, $width, $height);

        return $image;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 82);
        $jpeg = (string) ob_get_clean();

        imagedestroy($image);

        return $jpeg;
    }
}

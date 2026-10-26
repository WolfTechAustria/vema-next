<?php

namespace App\Services;

use RuntimeException;

/**
 * Favicon aus dem Vereinslogo: quadratisch eingepasst, als favicon.ico
 * (16, 32 und 48 Pixel) und als PNG für Handys (Startbildschirm). Die Dateien
 * werden einmal erzeugt und neben dem Logo zwischengespeichert; ein neues
 * Logo erzeugt neue Dateien. Ohne Logo bleibt das VEMA-Symbol.
 */
class ClubFavicon
{
    /**
     * Erlaubte PNG-Größen: 32 (Browser), 180 (Apple), 192 (Android).
     *
     * @var list<int>
     */
    public const PNG_SIZES = [32, 180, 192];

    private const ICO_SIZES = [16, 32, 48];

    /**
     * VEMA-Symbol für Installationen ohne Logo (und die Plattform-Domain).
     */
    public const FALLBACK_ICO = 'favicon-vemat.ico';

    private const CACHE_DIRECTORY = 'favicons';

    public function __construct(
        private ClubBranding $branding,
        private TenantManager $tenantManager,
    ) {}

    /**
     * Zeitstempel des Logos für Cache-freie Adressen; null ohne Logo.
     */
    public function version(): ?string
    {
        $logoPath = $this->logoPath();

        return $logoPath === null ? null : (string) filemtime($logoPath);
    }

    /**
     * Absoluter Pfad der favicon.ico — aus dem Logo oder das VEMA-Symbol.
     */
    public function icoPath(): string
    {
        $logoPath = $this->logoPath();

        if ($logoPath === null) {
            return public_path(self::FALLBACK_ICO);
        }

        return $this->cached($logoPath, 'favicon.ico', function () use ($logoPath): string {
            $images = array_map(fn (int $size) => $this->renderPng($logoPath, $size, false), self::ICO_SIZES);

            return $this->buildIco(array_combine(self::ICO_SIZES, $images));
        });
    }

    /**
     * Absoluter Pfad eines PNG-Symbols oder null ohne Logo. Für Apple mit
     * weißem Hintergrund, weil iOS transparente Flächen schwarz füllt.
     */
    public function pngPath(int $size): ?string
    {
        $logoPath = $this->logoPath();

        if ($logoPath === null || ! in_array($size, self::PNG_SIZES, true)) {
            return null;
        }

        $opaque = $size === 180;

        return $this->cached($logoPath, 'icon-'.$size.'.png', fn (): string => $this->renderPng($logoPath, $size, $opaque));
    }

    private function logoPath(): ?string
    {
        // Plattform-Domain: kein Verein, also auch kein Logo.
        if ($this->tenantManager->isMultiTenant() && $this->tenantManager->current() === null) {
            return null;
        }

        return $this->branding->logoPath();
    }

    /**
     * @param  callable(): string  $generate
     */
    private function cached(string $logoPath, string $fileName, callable $generate): string
    {
        $disk = $this->branding->disk();
        $directory = self::CACHE_DIRECTORY.'/'.filemtime($logoPath).'-'.substr(md5($logoPath), 0, 8);
        $relativePath = $directory.'/'.$fileName;

        if (! $disk->exists($relativePath)) {
            // Symbole eines früheren Logos aufräumen.
            foreach ($disk->directories(self::CACHE_DIRECTORY) as $oldDirectory) {
                if ($oldDirectory !== $directory) {
                    $disk->deleteDirectory($oldDirectory);
                }
            }

            $disk->put($relativePath, $generate());
        }

        return $disk->path($relativePath);
    }

    private function renderPng(string $logoPath, int $size, bool $opaque): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($logoPath));

        if ($source === false) {
            throw new RuntimeException('Das Logo konnte nicht gelesen werden.');
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, $opaque ? imagecolorallocate($canvas, 255, 255, 255) : imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $padding = (int) round($size * ($opaque ? 0.12 : 0.04));
        $box = $size - 2 * $padding;
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min($box / $width, $box / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        imagecopyresampled(
            $canvas,
            $source,
            intdiv($size - $targetWidth, 2),
            intdiv($size - $targetHeight, 2),
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        ob_start();
        imagepng($canvas, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * ICO-Datei mit eingebetteten PNG-Bildern (von allen Browsern unterstützt).
     *
     * @param  array<int, string>  $pngImages  Größe → PNG-Daten
     */
    private function buildIco(array $pngImages): string
    {
        $header = pack('vvv', 0, 1, count($pngImages));
        $directory = '';
        $data = '';
        $offset = 6 + 16 * count($pngImages);

        foreach ($pngImages as $size => $png) {
            $directory .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset);
            $data .= $png;
            $offset += strlen($png);
        }

        return $header.$directory.$data;
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Logo, Briefpapier und Unterschriften des Vereins. Die Dateien liegen auf
 * der Disk "branding", die relativen Pfade in tb_settings.
 */
class ClubBranding
{
    /**
     * Upload-Slot → Spalte in tb_settings und Dateiname ohne Endung.
     *
     * @var array<string, array{column: string, basename: string}>
     */
    public const SLOTS = [
        'logo' => ['column' => 'logo_path', 'basename' => 'logo'],
        'letterhead' => ['column' => 'letterhead_path', 'basename' => 'letterhead'],
        'signature_1' => ['column' => 'signatory_1_image_path', 'basename' => 'signature-1'],
        'signature_2' => ['column' => 'signatory_2_image_path', 'basename' => 'signature-2'],
    ];

    public function disk(): Filesystem
    {
        return Storage::disk('branding');
    }

    /**
     * Absoluter Dateipfad (für DomPDF / FPDI) oder null, wenn nicht hinterlegt.
     */
    public function path(string $slot): ?string
    {
        $relativePath = Setting::current()->{$this->column($slot)};

        if (blank($relativePath) || ! $this->disk()->exists($relativePath)) {
            return null;
        }

        return $this->disk()->path($relativePath);
    }

    public function logoPath(): ?string
    {
        return $this->path('logo');
    }

    public function letterheadPath(): ?string
    {
        return $this->path('letterhead');
    }

    /**
     * Öffentliche, absolute URL des Logos (Login-Seiten, E-Mail-Signatur).
     * Der Zeitstempel im Query-String sorgt nach einem neuen Upload für
     * frische Bilder trotz Browser-Cache.
     */
    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        if ($path === null) {
            return null;
        }

        return route('branding.logo', ['v' => filemtime($path)]);
    }

    /**
     * Unterschriften für Briefe — nur Einträge mit Funktion oder Name.
     *
     * @return array<int, array{title: string, name: string, imagePath: ?string}>
     */
    public function signatories(): array
    {
        $setting = Setting::current();
        $signatories = [];

        foreach ([1, 2] as $number) {
            $title = (string) $setting->{"signatory_{$number}_title"};
            $name = (string) $setting->{"signatory_{$number}_name"};

            if ($title === '' && $name === '') {
                continue;
            }

            $signatories[] = [
                'title' => $title,
                'name' => $name,
                'imagePath' => $this->path("signature_{$number}"),
            ];
        }

        return $signatories;
    }

    /**
     * Speichert eine hochgeladene Datei im Slot und ersetzt die bisherige.
     */
    public function store(string $slot, UploadedFile $file): string
    {
        $this->delete($slot);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $relativePath = self::SLOTS[$slot]['basename'].'.'.$extension;

        $this->disk()->putFileAs('', $file, $relativePath);

        Setting::current()->update([$this->column($slot) => $relativePath]);

        return $relativePath;
    }

    public function delete(string $slot): void
    {
        $setting = Setting::current();
        $relativePath = $setting->{$this->column($slot)};

        if (filled($relativePath)) {
            $this->disk()->delete($relativePath);
        }

        $setting->update([$this->column($slot) => null]);
    }

    private function column(string $slot): string
    {
        if (! isset(self::SLOTS[$slot])) {
            throw new InvalidArgumentException("Unbekannter Branding-Slot: {$slot}");
        }

        return self::SLOTS[$slot]['column'];
    }
}

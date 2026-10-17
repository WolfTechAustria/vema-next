<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Profilbilder der Mitglieder. Der Ausschnitt wird im Browser gewählt; hier
 * wird das Bild nur noch auf 400×400 gebracht und als JPEG neu gespeichert —
 * das entfernt Metadaten (z. B. GPS) und stellt sicher, dass es ein Bild ist.
 */
class MemberPhotoService
{
    public const SIZE = 400;

    public function store(Member $member, UploadedFile $upload): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        // Falls doch kein Quadrat ankommt: mittig zuschneiden.
        $photo = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 255, 255, 255));
        imagecopyresampled(
            $photo,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            self::SIZE,
            self::SIZE,
            $side,
            $side
        );

        ob_start();
        imagejpeg($photo, null, 85);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($photo);

        $path = 'member-photos/'.$member->memberID.'/'.Str::random(20).'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        $previousPath = $member->photo_path;

        $member->update(['photo_path' => $path]);

        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    public function remove(Member $member): void
    {
        if ($member->photo_path) {
            Storage::disk('local')->delete($member->photo_path);
        }

        $member->update(['photo_path' => null]);
    }

    /**
     * Adresse des Profilbilds für Funktionäre ('staff') bzw. für das
     * angemeldete Mitglied ('portal'); null ohne Bild. Der Dateiname dient
     * als Version, damit Browser ein neues Bild sofort laden.
     */
    public function url(?Member $member, string $context = 'staff'): ?string
    {
        if (! $member?->hasPhoto()) {
            return null;
        }

        $version = substr(md5($member->photo_path), 0, 10);

        return $context === 'portal'
            ? route('member.photo', ['v' => $version])
            : route('members.photo', ['member' => $member, 'v' => $version]);
    }
}

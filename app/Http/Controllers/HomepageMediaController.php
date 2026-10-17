<?php

namespace App\Http\Controllers;

use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\PostAttachment;
use App\Models\PostImage;
use App\Services\ClubBranding;
use App\Services\HomepageBoard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bilder der öffentlichen Vereinshomepage. Ausgeliefert wird nur, was auch
 * öffentlich sichtbar ist — Bilder von Entwürfen sehen nur angemeldete
 * Homepage-Verantwortliche (Vorschau).
 */
class HomepageMediaController extends Controller
{
    private const PUBLIC_CACHE = 'public, max-age=604800';

    public function postImage(PostImage $image, string $size): StreamedResponse
    {
        $image->loadMissing('post');

        abort_unless($image->post->isPublic() || $this->canPreview(), 404);

        return $this->file($size === 'gross' ? $image->file_path : $image->thumb_path, $image->post->isPublic());
    }

    public function hero(): StreamedResponse
    {
        $path = HomepageSetting::current()->hero_image_path;

        abort_if(blank($path), 404);

        return $this->file($path);
    }

    public function logo(ClubBranding $branding): BinaryFileResponse
    {
        $path = $branding->logoPath();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function boardPhoto(int $member, HomepageBoard $board): StreamedResponse
    {
        abort_unless(HomepageSetting::current()->show_board && HomepageSetting::current()->show_board_photos, 404);
        abort_unless($board->isPublicMember($member), 404);

        $path = Member::query()->whereKey($member)->value('photo_path');

        abort_if(blank($path), 404);

        return $this->file($path);
    }

    public function attachment(PostAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('post');

        abort_unless($attachment->post->isPublic() || $this->canPreview(), 404);

        return $this->pdf($attachment->file_path, $attachment->label, $attachment->post->isPublic());
    }

    public function announcement(string $kind): StreamedResponse
    {
        $settings = HomepageSetting::current();

        abort_unless($settings->announcement_enabled || $this->canPreview(), 404);

        if ($kind === 'datei') {
            abort_if(blank($settings->announcement_file_path), 404);

            return $this->pdf($settings->announcement_file_path, $settings->announcement_title ?: 'Einladung');
        }

        abort_if(blank($settings->announcement_image_path), 404);

        return $this->file($settings->announcement_image_path);
    }

    public function section(HomepageSection $section, string $kind): StreamedResponse
    {
        abort_unless($section->isPublic() || $this->canPreview(), 404);

        $path = match ($kind) {
            'bild' => $section->image_path,
            'bild2' => $section->image2_path,
            default => $section->file_path,
        };

        abort_if(blank($path), 404);

        return $kind === 'datei'
            ? $this->pdf($path, $section->value('button_label') ?: ($section->heading ?: 'Download'))
            : $this->file($path);
    }

    /**
     * PDF im Browser öffnen, mit sprechendem Dateinamen.
     */
    private function pdf(string $path, string $label, bool $isPublic = true): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        $fileName = (Str::slug($label, '-', 'de') ?: 'download').'.pdf';

        return Storage::disk('local')->response($path, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => $isPublic ? self::PUBLIC_CACHE : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function file(string $path, bool $isPublic = true): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => $isPublic ? self::PUBLIC_CACHE : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function canPreview(): bool
    {
        return auth()->guard('web')->user()?->hasAnyRole(['admin', 'homepage']) ?? false;
    }
}

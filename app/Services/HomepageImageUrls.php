<?php

namespace App\Services;

use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\PostAttachment;
use App\Models\PostImage;

/**
 * Öffentliche Adressen der Homepage-Bilder. Der Dateiname dient als Version,
 * damit Browser ein ausgetauschtes Bild sofort laden.
 */
class HomepageImageUrls
{
    public function __construct(private ClubBranding $branding) {}

    public function postImage(PostImage $image, string $size = 'klein'): string
    {
        return route('homepage.images.show', [
            'image' => $image->postImageID,
            'size' => $size,
            'v' => $this->version($image->file_path),
        ]);
    }

    public function hero(HomepageSetting $settings): ?string
    {
        if (blank($settings->hero_image_path)) {
            return null;
        }

        return route('homepage.hero', ['v' => $this->version($settings->hero_image_path)]);
    }

    public function logo(): ?string
    {
        $path = $this->branding->logoPath();

        return $path === null ? null : route('homepage.logo', ['v' => filemtime($path)]);
    }

    public function boardPhoto(Member $member): ?string
    {
        if (! $member->hasPhoto()) {
            return null;
        }

        return route('homepage.board.photo', [
            'member' => $member->memberID,
            'v' => $this->version($member->photo_path),
        ]);
    }

    public function attachment(PostAttachment $attachment): string
    {
        return route('homepage.attachments.show', [
            'attachment' => $attachment->postAttachmentID,
            'v' => $this->version($attachment->file_path),
        ]);
    }

    public function announcementImage(HomepageSetting $settings): ?string
    {
        return blank($settings->announcement_image_path)
            ? null
            : route('homepage.announcement', ['kind' => 'bild', 'v' => $this->version($settings->announcement_image_path)]);
    }

    /**
     * Ziel des Ankündigungs-Buttons: hochgeladenes PDF vor eingetragenem Link.
     */
    public function announcementLink(HomepageSetting $settings): ?string
    {
        if (filled($settings->announcement_file_path)) {
            return route('homepage.announcement', ['kind' => 'datei', 'v' => $this->version($settings->announcement_file_path)]);
        }

        return HomepageHosts::link($settings->announcement_link_url);
    }

    /**
     * Bild ('bild', 'bild2') oder Datei ('datei') eines Abschnitts.
     */
    public function sectionMedia(HomepageSection $section, string $kind): ?string
    {
        $path = match ($kind) {
            'bild' => $section->image_path,
            'bild2' => $section->image2_path,
            default => $section->file_path,
        };

        return blank($path)
            ? null
            : route('homepage.sections.media', ['section' => $section->sectionID, 'kind' => $kind, 'v' => $this->version($path)]);
    }

    /**
     * Ziel des Buttons eines Abschnitts: PDF, dann Mitgliederbereich, dann Link.
     */
    public function sectionButton(HomepageSection $section): ?string
    {
        if (filled($section->file_path)) {
            return $this->sectionMedia($section, 'datei');
        }

        if ($section->value('button_member_area') && ($memberAreaUrl = app(HomepageHosts::class)->memberAreaUrl())) {
            return $memberAreaUrl;
        }

        return HomepageHosts::link($section->value('button_url'));
    }

    private function version(string $path): string
    {
        return substr(md5($path), 0, 10);
    }
}

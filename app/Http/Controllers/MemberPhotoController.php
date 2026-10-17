<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\ActiveMemberResolver;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberPhotoController extends Controller
{
    /**
     * Profilbild eines Mitglieds für Funktionäre.
     */
    public function show(Member $member): StreamedResponse
    {
        return $this->respond($member);
    }

    /**
     * Profilbild des angemeldeten Mitglieds im Mitgliederbereich.
     */
    public function own(ActiveMemberResolver $activeMemberResolver): StreamedResponse
    {
        return $this->respond($activeMemberResolver->resolve());
    }

    private function respond(Member $member): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($member->photo_path && $disk->exists($member->photo_path), 404);

        // Die URL ist über ?v= versioniert, daher darf der Browser lange cachen.
        return $disk->response($member->photo_path, 'profilbild.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

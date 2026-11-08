<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\ActiveMemberResolver;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryPhotoController extends Controller
{
    /**
     * Foto eines Materialstücks für Admins und Zeugwarte.
     */
    public function show(InventoryItem $item): StreamedResponse
    {
        return $this->respond($item);
    }

    /**
     * Foto für das angemeldete Mitglied — nur von Material, das es hat oder hatte.
     */
    public function own(InventoryItem $item, ActiveMemberResolver $activeMemberResolver): StreamedResponse
    {
        abort_unless(
            $item->loans()->where('memberID', $activeMemberResolver->resolve()->memberID)->exists(),
            404
        );

        return $this->respond($item);
    }

    private function respond(InventoryItem $item): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($item->photo_path && $disk->exists($item->photo_path), 404);

        // Die URL ist über ?v= versioniert, daher darf der Browser lange cachen.
        return $disk->response($item->photo_path, 'material.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

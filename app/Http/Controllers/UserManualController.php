<?php

namespace App\Http\Controllers;

use App\Services\UserManual;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserManualController extends Controller
{
    /**
     * Liefert einen Screenshot des Benutzerhandbuchs aus.
     */
    public function image(UserManual $manual, string $image): BinaryFileResponse
    {
        $path = $manual->imagePath($image);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ClubBranding;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingController extends Controller
{
    /**
     * Liefert das Vereinslogo öffentlich aus — E-Mail-Programme und die
     * Login-Seiten laden es ohne Anmeldung.
     */
    public function logo(ClubBranding $branding): BinaryFileResponse
    {
        $path = $branding->logoPath();

        abort_if($path === null, 404);

        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

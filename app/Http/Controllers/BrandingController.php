<?php

namespace App\Http\Controllers;

use App\Services\ClubBranding;
use App\Services\ClubFavicon;
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

    /**
     * favicon.ico aus dem Vereinslogo (ohne Logo: VEMA-Symbol) — für die App
     * und die Vereinshomepage.
     */
    public function favicon(ClubFavicon $favicon): BinaryFileResponse
    {
        return response()->file($favicon->icoPath(), [
            'Content-Type' => 'image/x-icon',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function icon(int $size, ClubFavicon $favicon): BinaryFileResponse
    {
        $path = $favicon->pngPath($size);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}

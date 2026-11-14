<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ClubBranding;
use App\Services\ClubFavicon;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Web-App-Manifest (installierbare PWA) mit Vereinsname und -logo.
     * "?bereich=mitglied" startet im Mitgliederbereich.
     */
    public function manifest(Request $request, ClubFavicon $favicon, TenantManager $tenants): JsonResponse
    {
        $clubName = $tenants->isMultiTenant() && $tenants->current() === null
            ? 'VEMA'
            : (Setting::current()->name ?: 'VEMA');

        $forMembers = $request->query('bereich') === 'mitglied';
        $version = $favicon->version();

        $icons = $version
            ? collect([192, 512])->map(fn (int $size) => [
                'src' => route('branding.icon', ['size' => $size, 'v' => $version]),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
            ])->all()
            : collect([192, 512])->map(fn (int $size) => [
                'src' => asset('images/pwa/icon-'.$size.'.png'),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ])->all();

        return response()->json([
            'id' => $forMembers ? '/member/profile' : '/dashboard',
            'name' => $forMembers ? $clubName.' – Mitgliederbereich' : $clubName.' – VEMA',
            'short_name' => mb_strimwidth($clubName, 0, 24, '…'),
            'start_url' => $forMembers ? '/member/profile' : '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => '#0f172a',
            'lang' => 'de',
            'icons' => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

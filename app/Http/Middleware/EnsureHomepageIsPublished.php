<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Models\HomepageSetting;
use App\Services\PlanEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentliche Homepage-Routen: nur erreichbar, wenn das Paket die Homepage
 * enthält und der Verein sie eingeschaltet hat. Angemeldete Homepage-
 * Verantwortliche sehen sie schon vorher (Vorschau).
 */
class EnsureHomepageIsPublished
{
    public function __construct(private PlanEntitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->entitlements->allows(Feature::Homepage), 404);

        $canPreview = auth()->guard('web')->user()?->hasAnyRole(['admin', 'homepage']) ?? false;

        abort_unless(HomepageSetting::current()->enabled || $canPreview, 404);

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\PlatformNews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Veröffentlichte Neuigkeiten für eigene Installationen (z. B. Angerberg).
 * Zugriff nur mit einem Schlüssel aus TENANCY_NEWS_FEED_TOKENS.
 */
class NewsFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->isAuthorized((string) $request->bearerToken()), 401);

        $news = PlatformNews::query()
            ->published()
            ->latest('published_at')
            ->get()
            ->map(fn (PlatformNews $news) => [
                'id' => $news->id,
                'title' => $news->title,
                'body' => $news->body,
                'min_plan' => $news->min_plan?->value,
                'published_at' => $news->published_at->toIso8601String(),
                'emailed' => $news->emailed_at !== null,
            ]);

        return response()->json(['data' => $news]);
    }

    private function isAuthorized(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        foreach (config('tenancy.news_feed_tokens') as $allowed) {
            if (hash_equals($allowed, $token)) {
                return true;
            }
        }

        return false;
    }
}

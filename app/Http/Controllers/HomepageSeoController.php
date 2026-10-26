<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Services\HomepageEvents;
use App\Services\HomepageHosts;
use App\Services\PlanEntitlements;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * robots.txt und sitemap.xml für Suchmaschinen.
 */
class HomepageSeoController extends Controller
{
    public function __construct(private HomepageHosts $hosts) {}

    /**
     * Auf der Homepage-Domain: alles erlaubt, mit Verweis auf die Sitemap
     * (solange die Homepage nicht veröffentlicht ist: nichts erlaubt).
     */
    public function homepageRobots(PlanEntitlements $entitlements): Response
    {
        $isPublic = $this->hosts->isActive()
            && $entitlements->allows(Feature::Homepage)
            && HomepageSetting::current()->enabled;

        if (! $isPublic) {
            return $this->appRobots();
        }

        return $this->plainText("User-agent: *\nAllow: /\n\nSitemap: ".$this->hosts->publicRoute('homepage.sitemap')."\n");
    }

    /**
     * Die App selbst (Anmeldung, Verwaltung, Vorschau) gehört nicht in Suchmaschinen.
     */
    public function appRobots(): Response
    {
        return $this->plainText("User-agent: *\nDisallow: /\n");
    }

    public function sitemap(HomepageEvents $events): Response
    {
        $settings = HomepageSetting::current();
        $latestPost = Post::query()->published()->max('updated_at');

        $entries = [
            ['url' => $this->hosts->publicRoute('homepage.home'), 'lastmod' => $latestPost],
            ['url' => $this->hosts->publicRoute('homepage.posts.index'), 'lastmod' => $latestPost],
        ];

        if ($events->enabled()) {
            $entries[] = ['url' => $this->hosts->publicRoute('homepage.events'), 'lastmod' => null];
        }

        if (filled($settings->about_html) || HomepageSection::query()->forAbout()->exists()) {
            $entries[] = ['url' => $this->hosts->publicRoute('homepage.about'), 'lastmod' => null];
        }

        if ($settings->show_board) {
            $entries[] = ['url' => $this->hosts->publicRoute('homepage.board'), 'lastmod' => null];
        }

        if ($settings->contact_visible) {
            $entries[] = ['url' => $this->hosts->publicRoute('homepage.contact'), 'lastmod' => null];
        }

        HomepagePage::query()->published()->ordered()->get(['slug', 'updated_at'])
            ->each(function (HomepagePage $page) use (&$entries): void {
                $entries[] = ['url' => $this->hosts->publicRoute('homepage.page', ['slug' => $page->slug]), 'lastmod' => $page->updated_at];
            });

        Post::query()->published()->orderByDesc('published_at')->get(['slug', 'updated_at'])
            ->each(function (Post $post) use (&$entries): void {
                $entries[] = ['url' => $this->hosts->publicRoute('homepage.posts.show', ['slug' => $post->slug]), 'lastmod' => $post->updated_at];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= '  <url><loc>'.e($entry['url']).'</loc>';

            if ($entry['lastmod'] !== null) {
                $xml .= '<lastmod>'.Carbon::parse($entry['lastmod'])->toAtomString().'</lastmod>';
            }

            $xml .= "</url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Unbekannte Adresse; ServeHomepageHost sucht danach eine Weiterleitung.
     */
    public function notFound(): never
    {
        abort(404);
    }

    private function plainText(string $content): Response
    {
        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

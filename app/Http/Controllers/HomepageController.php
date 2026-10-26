<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\Setting;
use App\Services\HomepageBoard;
use App\Services\HomepageEvents;
use App\Services\HomepageHosts;
use App\Services\HomepageImageUrls;
use App\Services\HomepageStructuredData;
use App\Services\HtmlSanitizer;
use App\Services\PlanEntitlements;
use App\Services\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Öffentliche Seiten der Vereinshomepage. Alle Seiten nutzen dieselben
 * Inhalte; die gewählte Vorlage bestimmt nur das Layout.
 */
class HomepageController extends Controller
{
    private const POSTS_PER_PAGE = 9;

    public function __construct(
        private HtmlSanitizer $sanitizer,
        private HomepageImageUrls $imageUrls,
        private HomepageHosts $hosts,
        private HomepageEvents $events,
        private HomepageStructuredData $structuredData,
    ) {}

    public function home(): View
    {
        $posts = Post::query()
            ->published()
            ->with('coverImage')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        $upcomingEvents = $this->events->upcoming(3);
        $club = Setting::current();

        return $this->render('home', [
            'posts' => $posts,
            'upcomingEvents' => $upcomingEvents,
            'structuredData' => [
                $this->structuredData->organization($club),
                ...$this->structuredData->events($upcomingEvents, $club),
            ],
        ]);
    }

    public function posts(Request $request): View
    {
        $years = Post::query()
            ->published()
            ->pluck('published_at')
            ->map(fn ($publishedAt) => $publishedAt->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        $year = $years->contains((string) $request->query('jahr')) ? (string) $request->query('jahr') : null;

        $posts = Post::query()
            ->published()
            ->with('coverImage')
            ->when($year, fn ($query) => $query->whereBetween('published_at', [$year.'-01-01 00:00:00', $year.'-12-31 23:59:59']))
            ->orderByDesc('published_at')
            ->paginate(self::POSTS_PER_PAGE)
            ->withQueryString();

        $settings = HomepageSetting::current();

        return $this->render('posts', [
            'posts' => $posts,
            'years' => $years,
            'selectedYear' => $year,
            'pageTitle' => $settings->newsLabel(),
            'pageHeading' => $settings->pageHeading('news'),
        ]);
    }

    public function post(string $slug): View
    {
        $post = Post::query()
            ->published()
            ->with(['images', 'attachments'])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->renderPost($post);
    }

    /**
     * Auch für die Vorschau im Verwaltungsbereich (Entwürfe).
     */
    public function renderPost(Post $post, bool $isPreview = false): View
    {
        $description = $post->seo_description ?: $post->teaser(160);

        return $this->render('post', [
            'post' => $post,
            'bodyHtml' => $this->sanitizer->sanitize($post->body_html),
            'pageTitle' => $post->title,
            'seoTitle' => $post->seo_title,
            'metaDescription' => $description,
            'structuredData' => [$this->structuredData->article($post, Setting::current(), $description)],
            'ogImage' => $post->images->first() ? $this->imageUrls->postImage($post->images->first(), 'gross') : null,
            'isPreview' => $isPreview,
        ]);
    }

    public function events(): View
    {
        abort_unless($this->events->enabled(), 404);

        $upcoming = $this->events->upcoming();

        return $this->render('events', [
            'structuredData' => $this->structuredData->events($upcoming, Setting::current()),
            'upcomingByMonth' => $upcoming->groupBy(fn ($event) => $event->starts_at->locale('de')->translatedFormat('F Y')),
            'pastEvents' => $this->events->past(),
            'pageTitle' => 'Termine',
            'pageHeading' => HomepageSetting::current()->pageHeading('events'),
        ]);
    }

    public function about(): View
    {
        $settings = HomepageSetting::current();

        $sections = HomepageSection::query()->forAbout()->topLevel()->with('children')->ordered()->get();

        abort_if(blank($settings->about_html) && $sections->isEmpty(), 404);

        return $this->render('about', [
            'aboutHtml' => $this->sanitizer->sanitize($settings->about_html),
            'sections' => $sections,
            'pageTitle' => 'Über uns',
            'pageHeading' => $settings->pageHeading('about'),
        ]);
    }

    public function board(HomepageBoard $board): View
    {
        $settings = HomepageSetting::current();

        abort_unless($settings->show_board, 404);

        return $this->render('board', [
            'boardMembers' => $board->entries($settings->show_board_photos),
            'pageTitle' => 'Vorstand',
            'pageHeading' => $settings->pageHeading('board'),
        ]);
    }

    public function contact(): View
    {
        $settings = HomepageSetting::current();

        abort_unless($settings->contact_visible, 404);

        return $this->render('contact', [
            'pageTitle' => 'Kontakt',
            'pageHeading' => $settings->pageHeading('contact'),
        ]);
    }

    public function page(string $slug): View
    {
        $page = HomepagePage::query()
            ->published()
            ->where('slug', $slug)
            ->with('sections.children')
            ->firstOrFail();

        return $this->render('page', [
            'page' => $page,
            'sections' => $page->sections,
            'pageHeading' => $page->subtitle,
            'bodyHtml' => $this->sanitizer->sanitize($page->body_html),
            'pageTitle' => $page->title,
            'seoTitle' => $page->seo_title,
            'metaDescription' => $page->seo_description ?: HomepageSetting::current()->meta_description,
        ]);
    }

    /**
     * „Seite nicht gefunden“ im Design der Homepage — für jede 404 auf der
     * Homepage-Domain bzw. in der Vorschau unter /site (siehe bootstrap/app.php).
     * Null, wenn die Homepage nicht sichtbar ist: dann die allgemeine Fehlerseite.
     */
    public function notFoundResponse(Request $request): ?Response
    {
        $isHomepageRequest = $this->hosts->isActive() || $request->is(HomepageHosts::PATH_PREFIX, HomepageHosts::PATH_PREFIX.'/*');

        if (! $isHomepageRequest || $request->expectsJson()) {
            return null;
        }

        try {
            $tenantManager = app(TenantManager::class);

            if ($tenantManager->isMultiTenant() && $tenantManager->current() === null) {
                return null;
            }

            $canPreview = auth()->guard('web')->user()?->hasAnyRole(['admin', 'homepage']) ?? false;

            if (! app(PlanEntitlements::class)->allows(Feature::Homepage) || ! (HomepageSetting::current()->enabled || $canPreview)) {
                return null;
            }

            $posts = Post::query()->published()->with('coverImage')->orderByDesc('published_at')->limit(3)->get();

            return response($this->render('not-found', [
                'posts' => $posts,
                'pageTitle' => 'Seite nicht gefunden',
                'metaDescription' => null,
                'canonicalUrl' => null,
                'isPreview' => ! $this->hosts->isActive(),
            ]), 404);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $page, array $data = []): View
    {
        $settings = HomepageSetting::current();
        $isPreview = (bool) ($data['isPreview'] ?? false);
        $pages = HomepagePage::query()->published()->ordered()->get(['pageID', 'title', 'slug', 'in_menu']);

        $templatePath = 'homepage.templates.'.$settings->template->value;
        $hasSections = HomepageSection::query()->forAbout()->exists();

        // Eine Vorlage kann einzelne Seiten komplett selbst gestalten.
        $view = view()->exists($templatePath.'.pages.'.$page) ? $templatePath.'.pages.'.$page : 'homepage.pages.'.$page;

        return view($view, array_merge([
            'settings' => $settings,
            'club' => Setting::current(),
            'templatePath' => $templatePath,
            'memberAreaUrl' => $settings->show_member_area_link ? $this->hosts->memberAreaUrl() : null,
            'pageHeading' => null,
            'sections' => collect(),
            'navigation' => $this->navigation($settings, $pages->where('in_menu', true), $hasSections),
            'footerPages' => $pages->where('in_menu', false),
            'imageUrls' => $this->imageUrls,
            'logoUrl' => $this->imageUrls->logo(),
            'heroUrl' => $this->imageUrls->hero($settings),
            'pageTitle' => null,
            'seoTitle' => null,
            'metaDescription' => $settings->meta_description,
            'ogImage' => null,
            'canonicalUrl' => $isPreview ? null : $this->canonicalUrl(),
            'structuredData' => [],
            'isPreview' => false,
        ], $data));
    }

    /**
     * Maßgebliche Adresse der Seite auf der Homepage-Domain — ohne /site und
     * ohne Abfrageparameter außer Jahr und Seitenzahl der News.
     */
    private function canonicalUrl(): string
    {
        $query = array_filter([
            'jahr' => request()->query('jahr'),
            'page' => (int) request()->query('page') > 1 ? (int) request()->query('page') : null,
        ], fn ($value) => is_scalar($value) && $value !== '');

        $url = $this->hosts->publicUrl('/'.request()->path());

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    /**
     * Menüpunkte: feste Seiten (soweit aktiviert) und eigene Seiten im Menü.
     *
     * @param  Collection<int, HomepagePage>  $menuPages
     * @return list<array{label: string, url: string, active: bool}>
     */
    private function navigation(HomepageSetting $settings, Collection $menuPages, bool $hasAboutSections): array
    {
        $items = [
            ['label' => 'Start', 'url' => route('homepage.home'), 'active' => request()->routeIs('homepage.home')],
            ['label' => $settings->newsLabel(), 'url' => route('homepage.posts.index'), 'active' => request()->routeIs('homepage.posts.*', 'homepage-admin.posts.preview')],
        ];

        if ($this->events->enabled()) {
            $items[] = ['label' => 'Termine', 'url' => route('homepage.events'), 'active' => request()->routeIs('homepage.events')];
        }

        if (filled($settings->about_html) || $hasAboutSections) {
            $items[] = ['label' => 'Über uns', 'url' => route('homepage.about'), 'active' => request()->routeIs('homepage.about')];
        }

        if ($settings->show_board) {
            $items[] = ['label' => 'Vorstand', 'url' => route('homepage.board'), 'active' => request()->routeIs('homepage.board')];
        }

        foreach ($menuPages as $menuPage) {
            $items[] = [
                'label' => $menuPage->title,
                'url' => route('homepage.page', $menuPage->slug),
                'active' => request()->routeIs('homepage.page') && request()->route('slug') === $menuPage->slug,
            ];
        }

        if ($settings->contact_visible) {
            $items[] = ['label' => 'Kontakt', 'url' => route('homepage.contact'), 'active' => request()->routeIs('homepage.contact')];
        }

        return $items;
    }
}

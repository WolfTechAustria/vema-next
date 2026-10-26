<?php

use App\Enums\HomepageTemplate;
use App\Enums\PostStatus;
use App\Livewire\Homepage\Pages\Index as PagesIndex;
use App\Livewire\Homepage\Posts\Edit as PostsEdit;
use App\Livewire\Homepage\Seo;
use App\Models\ClubEvent;
use App\Models\HomepageNotFound;
use App\Models\HomepagePage;
use App\Models\HomepageRedirect;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\HomepageHosts;
use Livewire\Livewire;

const HOMEPAGE = 'http://mv-angerberg.at';

beforeEach(function () {
    HomepageSetting::current()->update(['enabled' => true, 'domain' => 'mv-angerberg.at', 'meta_description' => 'Unser Musikverein']);
    app(HomepageHosts::class)->forgetSingleDomain();
});

describe('sitemap and robots.txt', function () {
    it('lists public pages and posts with their public address', function () {
        $post = Post::factory()->create(['title' => 'Konzertbericht']);
        $draft = Post::factory()->create(['title' => 'Geheim', 'status' => PostStatus::Draft]);
        $page = HomepagePage::factory()->create(['slug' => 'mitglied-werden']);
        HomepagePage::factory()->draft()->create(['slug' => 'entwurf']);

        $this->get(HOMEPAGE.'/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://mv-angerberg.at</loc>', false)
            ->assertSee('<loc>https://mv-angerberg.at/news</loc>', false)
            ->assertSee('<loc>https://mv-angerberg.at/news/'.$post->slug.'</loc>', false)
            ->assertSee('<loc>https://mv-angerberg.at/'.$page->slug.'</loc>', false)
            ->assertDontSee($draft->slug)
            ->assertDontSee('entwurf')
            ->assertDontSee('.at/site', false);
    });

    it('allows crawling of the homepage domain and points to the sitemap', function () {
        $this->get(HOMEPAGE.'/robots.txt')
            ->assertOk()
            ->assertSee('Allow: /')
            ->assertSee('Sitemap: https://mv-angerberg.at/sitemap.xml');
    });

    it('blocks crawling while the homepage is not published', function () {
        HomepageSetting::current()->update(['enabled' => false]);

        $this->get(HOMEPAGE.'/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /')
            ->assertDontSee('Sitemap');
    });

    it('keeps the app itself out of search engines', function () {
        $this->get('http://localhost/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /');
    });
});

describe('page head', function () {
    it('adds a canonical address, share tags and the article data of a post', function () {
        $post = Post::factory()->create(['title' => 'Konzertbericht', 'excerpt' => 'Ein schöner Abend.']);

        $this->get(HOMEPAGE.'/news/'.$post->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://mv-angerberg.at/news/'.$post->slug.'">', false)
            ->assertSee('<meta name="description" content="Ein schöner Abend.">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('"headline":"Konzertbericht"', false);
    });

    it('keeps only year and page number in the canonical address of the news', function () {
        $this->get(HOMEPAGE.'/news?jahr=2025&page=2&utm_source=facebook')
            ->assertSee('<link rel="canonical" href="https://mv-angerberg.at/news?jahr=2025&amp;page=2">', false);
    });

    it('uses the own search title and description of posts and pages', function () {
        $post = Post::factory()->create(['title' => 'Konzertbericht', 'seo_title' => 'Frühjahrskonzert 2026', 'seo_description' => 'Rückblick auf das Konzert.']);
        $page = HomepagePage::factory()->create(['title' => 'Mitglied werden', 'slug' => 'mitglied-werden', 'seo_description' => 'So wirst du Mitglied.']);
        $club = Setting::current()->name;

        $this->get(HOMEPAGE.'/news/'.$post->slug)
            ->assertSee('<title>Frühjahrskonzert 2026 | '.e($club).'</title>', false)
            ->assertSee('content="Rückblick auf das Konzert."', false);

        $this->get(HOMEPAGE.'/mitglied-werden')
            ->assertSee('<title>Mitglied werden | '.e($club).'</title>', false)
            ->assertSee('content="So wirst du Mitglied."', false);

        HomepagePage::factory()->create(['slug' => 'impressum']);

        $this->get(HOMEPAGE.'/impressum')->assertSee('content="Unser Musikverein"', false);
    });

    it('describes the club on the home page and events on the events page', function () {
        HomepageSetting::current()->update(['show_events' => true]);
        ClubEvent::factory()->onHomepage()->create(['title' => 'Sommerfest', 'location' => 'Schützenheim']);

        $this->get(HOMEPAGE.'/')
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('"@type":"Event"', false);

        $this->get(HOMEPAGE.'/termine')
            ->assertSee('"name":"Sommerfest"', false)
            ->assertSee('"name":"Schützenheim"', false);
    });

    it('describes gallery images without caption by the post title', function () {
        $post = Post::factory()->create(['title' => 'Konzertbericht']);
        $post->images()->createMany([
            ['file_path' => 'a.jpg', 'thumb_path' => 'a-klein.jpg', 'sort' => 1],
            ['file_path' => 'b.jpg', 'thumb_path' => 'b-klein.jpg', 'sort' => 2],
            ['file_path' => 'c.jpg', 'thumb_path' => 'c-klein.jpg', 'sort' => 3, 'caption' => 'Applaus'],
        ]);

        $this->get(HOMEPAGE.'/news/'.$post->slug)
            ->assertSee('alt="Konzertbericht"', false)
            ->assertSee('alt="Konzertbericht – Bild 2"', false)
            ->assertSee('alt="Applaus"', false);
    });

    it('has no canonical address in the preview of a draft', function () {
        $post = Post::factory()->create(['status' => PostStatus::Draft]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('homepage-admin.posts.preview', $post))
            ->assertOk()
            ->assertDontSee('rel="canonical"', false);
    });
});

describe('redirects of old addresses', function () {
    it('follows configured redirects and counts their use', function () {
        $redirect = HomepageRedirect::factory()->create(['source_path' => '/ergebnisse', 'target' => '/news']);
        HomepageRedirect::factory()->create(['source_path' => '/files/*', 'target' => 'https://archiv.mv-angerberg.at']);

        $this->get(HOMEPAGE.'/Ergebnisse/')->assertStatus(301)->assertRedirect(HOMEPAGE.'/news');
        $this->get(HOMEPAGE.'/files/2008-vereinsmeisterschaft.pdf')->assertRedirect('https://archiv.mv-angerberg.at');

        expect($redirect->fresh()->hits)->toBe(1);
    });

    it('recognises typical addresses of the former homepage', function (string $oldPath, string $target) {
        Post::factory()->create(['slug' => 'dorfmeisterschaft-2024']);
        Post::factory()->create(['slug' => 'bezirksmeisterschaft-2022-23-563']);
        Post::factory()->create(['slug' => 'neuer-name', 'legacy_path' => '/2019/06/alter-name']);
        HomepagePage::factory()->create(['slug' => 'mitglied-werden']);

        $this->get(HOMEPAGE.$oldPath)->assertStatus(301)->assertRedirect(HOMEPAGE.$target);
    })->with([
        'post archive' => ['/archive/dorfmeisterschaft-2024/', '/news/dorfmeisterschaft-2024'],
        'post feed' => ['/archive/dorfmeisterschaft-2024/feed/', '/news/dorfmeisterschaft-2024'],
        'elementor address' => ['/archive/elementor-563/', '/news/bezirksmeisterschaft-2022-23-563'],
        'imported address' => ['/2019/06/alter-name/', '/news/neuer-name'],
        'date archive' => ['/archive/2024/02/14/', '/news?jahr=2024'],
        'category' => ['/archive/category/wettkaempfe/', '/news'],
        'topic' => ['/thema/luftgewehr/', '/news'],
        'paged listing' => ['/aktuelles/page/3/', '/news'],
        'old html page' => ['/mitglied-werden.html', '/mitglied-werden'],
        'old start page' => ['/index.html', ''],
    ]);

    it('logs addresses it cannot resolve, but not files bots ask for', function () {
        $this->get(HOMEPAGE.'/bichler-markus---erfolge.html')->assertNotFound();
        $this->get(HOMEPAGE.'/bichler-markus---erfolge.html')->assertNotFound();
        $this->get(HOMEPAGE.'/news/gibt-es-nicht')->assertRedirect(HOMEPAGE.'/news');
        $this->get(HOMEPAGE.'/wp-login.php')->assertNotFound();

        expect(HomepageNotFound::query()->pluck('hits', 'path')->all())->toBe(['/bichler-markus---erfolge.html' => 2]);
    });

    it('does not redirect a hidden fixed page to itself', function () {
        HomepageSetting::current()->update(['show_board' => false]);

        $this->get(HOMEPAGE.'/vorstand')->assertNotFound();
    });

    it('leaves the preview on the app domain alone', function () {
        Post::factory()->create(['slug' => 'dorfmeisterschaft-2024']);

        $this->get('http://localhost/site/archive/dorfmeisterschaft-2024')->assertNotFound();

        expect(HomepageNotFound::query()->count())->toBe(0);
    });
});

describe('administration', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('opens the search engine page', function () {
        HomepageNotFound::query()->create(['path' => '/alte-seite.html', 'hits' => 3, 'last_seen_at' => now()]);

        $this->get(route('homepage-admin.seo'))
            ->assertOk()
            ->assertSee('https://mv-angerberg.at/sitemap.xml')
            ->assertSee('/alte-seite.html');
    });

    it('creates redirects from not found addresses', function () {
        $entry = HomepageNotFound::query()->create(['path' => '/ergebnisse', 'hits' => 3, 'last_seen_at' => now()]);

        Livewire::test(Seo::class)
            ->call('redirectNotFound', $entry->notFoundID)
            ->assertSet('sourcePath', '/ergebnisse')
            ->set('target', '/news')
            ->call('saveRedirect')
            ->assertHasNoErrors();

        expect(HomepageRedirect::query()->sole()->only(['source_path', 'target']))->toBe(['source_path' => '/ergebnisse', 'target' => '/news'])
            ->and(HomepageNotFound::query()->exists())->toBeFalse();
    });

    it('validates redirects', function () {
        HomepageRedirect::factory()->create(['source_path' => '/ergebnisse']);

        Livewire::test(Seo::class)
            ->set('sourcePath', 'https://mv-angerberg.at/Ergebnisse/')
            ->set('target', 'news')
            ->call('saveRedirect')
            ->assertHasErrors(['sourcePath' => 'unique', 'target' => 'regex'])
            ->set('sourcePath', '/')
            ->call('saveRedirect')
            ->assertHasErrors(['sourcePath' => 'not_in']);
    });

    it('deletes redirects', function () {
        $redirect = HomepageRedirect::factory()->create();

        Livewire::test(Seo::class)->call('deleteRedirect', $redirect->redirectID);

        expect(HomepageRedirect::query()->exists())->toBeFalse();
    });

    it('saves the search title and description of a post and redirects its old address', function () {
        $post = Post::factory()->create(['title' => 'Konzert', 'slug' => 'konzert']);
        HomepageRedirect::factory()->create(['source_path' => '/archive/konzert', 'target' => '/news/konzert']);

        Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('seoTitle', 'Frühjahrskonzert')
            ->set('seoDescription', 'Rückblick')
            ->set('slug', 'fruehjahrskonzert')
            ->call('save')
            ->assertHasNoErrors();

        expect($post->fresh()->only(['seo_title', 'seo_description']))->toBe(['seo_title' => 'Frühjahrskonzert', 'seo_description' => 'Rückblick'])
            ->and(HomepageRedirect::query()->pluck('target', 'source_path')->all())->toBe([
                '/archive/konzert' => '/news/fruehjahrskonzert',
                '/news/konzert' => '/news/fruehjahrskonzert',
            ]);
    });

    it('does not redirect the address of a draft', function () {
        $post = Post::factory()->create(['slug' => 'entwurf', 'status' => PostStatus::Draft]);

        Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('slug', 'entwurf-neu')
            ->call('save');

        expect(HomepageRedirect::query()->exists())->toBeFalse();
    });

    it('saves the search fields of a page and redirects its old address', function () {
        $page = HomepagePage::factory()->create(['title' => 'Mitglied werden', 'slug' => 'mitglied']);

        Livewire::test(PagesIndex::class)
            ->call('editPage', $page->pageID)
            ->set('seoDescription', 'So wirst du Mitglied.')
            ->set('slug', 'mitglied-werden')
            ->call('savePage')
            ->assertHasNoErrors();

        expect($page->fresh()->seo_description)->toBe('So wirst du Mitglied.')
            ->and(HomepageRedirect::query()->sole()->only(['source_path', 'target']))->toBe(['source_path' => '/mitglied', 'target' => '/mitglied-werden']);
    });
});

describe('not found pages', function () {
    it('shows the not found page in the design of the homepage', function (HomepageTemplate $template) {
        HomepageSetting::current()->update(['template' => $template]);
        Post::factory()->create(['title' => 'Konzertbericht']);

        $this->get(HOMEPAGE.'/gibt-es-nicht')
            ->assertNotFound()
            ->assertSee('Diese Seite gibt es leider nicht.')
            ->assertSee('Zur Startseite')
            ->assertSee('Konzertbericht')
            ->assertSee('<title>Seite nicht gefunden | ', false)
            ->assertDontSee('rel="canonical"', false);
    })->with(HomepageTemplate::cases());

    it('also covers deeper unknown addresses', function () {
        $this->get(HOMEPAGE.'/gibt/es/nicht.pdf')
            ->assertNotFound()
            ->assertSee('Diese Seite gibt es leider nicht.');
    });

    it('shows the general page while the homepage is not published', function () {
        HomepageSetting::current()->update(['enabled' => false]);

        $this->get(HOMEPAGE.'/')
            ->assertNotFound()
            ->assertSee('Hier gibt es leider nichts.')
            ->assertDontSee('Diese Seite gibt es leider nicht.');
    });

    it('shows its own not found page in the app', function () {
        $this->get('http://localhost/gibt-es-nicht')
            ->assertNotFound()
            ->assertSee('Hier gibt es leider nichts.')
            ->assertSee('Zur Startseite');
    });
});

<?php

use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\News\Index as NewsIndex;
use App\Mail\VemaNewsMail;
use App\Models\User;
use App\Models\VemaNews;
use App\Services\PlatformNewsFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * Eigene Installation (Modus "single"): Neuigkeiten kommen per Feed von der Plattform.
 */

beforeEach(function () {
    Mail::fake();

    config([
        'tenancy.news_feed_url' => 'https://app.vemat.test/api/neuigkeiten',
        'tenancy.news_feed_token' => 'geheimer-schluessel',
    ]);

    $this->feedItems = [];
    Http::fake(['app.vemat.test/*' => fn () => Http::response(['data' => $this->feedItems])]);
});

function newsStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user;
}

/**
 * @param  list<array<string, mixed>>  $items
 */
function fakeNewsFeed(array $items): void
{
    test()->feedItems = $items;
}

/**
 * @return array<string, mixed>
 */
function feedItem(int $id, string $title, bool $emailed = false, string $publishedAt = '2026-11-14T10:00:00+01:00'): array
{
    return ['id' => $id, 'title' => $title, 'body' => "Neu sind **Ehrungen**.\n\n<script>alert(1)</script>", 'min_plan' => null, 'published_at' => $publishedAt, 'emailed' => $emailed];
}

it('takes over the news from the platform with the configured key', function () {
    fakeNewsFeed([feedItem(1, 'Dienste tauschen'), feedItem(2, 'SEPA-Lastschrift')]);

    $this->artisan('platform-news:pull')->assertSuccessful();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer geheimer-schluessel'));

    expect(VemaNews::query()->orderBy('remote_id')->pluck('title')->all())->toBe(['Dienste tauschen', 'SEPA-Lastschrift']);

    // Auf der Plattform zurückgezogene Neuigkeiten verschwinden, Änderungen kommen an.
    fakeNewsFeed([feedItem(2, 'SEPA-Lastschrift für Beiträge')]);

    $this->artisan('platform-news:pull')->assertSuccessful();

    expect(VemaNews::query()->pluck('title')->all())->toBe(['SEPA-Lastschrift für Beiträge']);
});

it('mails admins only news that were emailed on the platform, never the backlog of the first pull', function () {
    $admin = newsStaff('admin');
    newsStaff('kassier');

    fakeNewsFeed([feedItem(1, 'Alte Neuigkeit', emailed: true)]);
    $this->artisan('platform-news:pull')->assertSuccessful();

    Mail::assertNothingSent();

    fakeNewsFeed([
        feedItem(1, 'Alte Neuigkeit', emailed: true),
        feedItem(2, 'Ohne E-Mail'),
        feedItem(3, 'Mit E-Mail', emailed: true),
    ]);
    $this->artisan('platform-news:pull')->assertSuccessful();
    $this->artisan('platform-news:pull')->assertSuccessful();

    Mail::assertSent(VemaNewsMail::class, 1);
    Mail::assertSent(VemaNewsMail::class, fn (VemaNewsMail $mail) => $mail->hasTo($admin->email) && $mail->news->title === 'Mit E-Mail');
});

it('shows the news to admins with an unread marker', function () {
    fakeNewsFeed([feedItem(1, 'Dienste tauschen')]);
    $this->artisan('platform-news:pull')->assertSuccessful();

    $admin = newsStaff('admin');
    $treasurer = newsStaff('kassier');
    $feed = app(PlatformNewsFeed::class);

    expect($feed->isAvailableFor($treasurer))->toBeFalse()
        ->and($feed->unreadCount($admin))->toBe(1);

    Livewire::actingAs($admin, 'web')
        ->test(DashboardIndex::class)
        ->assertSee('Eine Neuigkeit');

    Livewire::actingAs($admin, 'web')
        ->test(NewsIndex::class)
        ->assertSee('Dienste tauschen')
        ->assertSee('<strong>Ehrungen</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);

    expect($feed->unreadCount($admin->fresh()))->toBe(0);
});

it('has no news without a feed key', function () {
    config(['tenancy.news_feed_token' => null]);

    $this->artisan('platform-news:pull')->assertSuccessful();

    Http::assertNothingSent();

    $this->actingAs(newsStaff('admin'), 'web')
        ->get(route('news.index'))
        ->assertNotFound();
});

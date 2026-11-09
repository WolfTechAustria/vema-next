<?php

use App\Enums\Plan;
use App\Livewire\Central\Admin\News;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\News\Index as NewsIndex;
use App\Mail\Central\PlatformNewsMail;
use App\Models\PlatformAdmin;
use App\Models\PlatformNews;
use App\Models\User;
use App\Services\PlatformNewsDrafts;
use App\Services\PlatformNewsFeed;
use App\Services\TenantManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 * Neuigkeiten zu VEMA: Plattform-Admin verfasst, Vereins-Admins lesen.
 */
uses(TestCase::class);

beforeEach(function () {
    bootMultiTenancyForTests();

    Mail::fake();

    // Mitgelieferte Neuigkeiten aus resources/news gezielt testen (siehe unten).
    $this->newsDirectory = storage_path('framework/testing/news-'.uniqid());
    File::ensureDirectoryExists($this->newsDirectory);
    app()->instance(PlatformNewsDrafts::class, new PlatformNewsDrafts($this->newsDirectory));

    $this->platformAdmin = PlatformAdmin::create([
        'name' => 'Betreiber',
        'email' => 'office@vemat.test',
        'password' => 'ein-sehr-sicheres-passwort',
    ]);

    $this->starter = createTenantWithDatabase('starter-verein', 'Starter Verein', ['plan' => Plan::Starter, 'contact_email' => 'starter@example.com']);
    $this->verein = createTenantWithDatabase('paket-verein', 'Paket Verein', ['plan' => Plan::Verein, 'contact_email' => 'verein@example.com']);
});

afterEach(function () {
    File::deleteDirectory($this->newsDirectory);
    shutdownMultiTenancyForTests();
});

function newsAdmin(): User
{
    $user = User::query()->first();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    return $user;
}

function writeNews(string $title, string $minPlan = '', bool $sendEmail = false, bool $publish = true): void
{
    Livewire::actingAs(test()->platformAdmin, 'platform')
        ->test(News::class)
        ->call('newNews')
        ->set('title', $title)
        ->set('body', "Neu sind:\n\n- **Ehrungen**\n- eigene Felder\n\n<script>alert(1)</script>")
        ->set('minPlan', $minPlan)
        ->set('sendEmail', $sendEmail)
        ->call($publish ? 'publish' : 'saveDraft')
        ->assertHasNoErrors();
}

it('shows published news to club admins until they read them', function () {
    writeNews('Ehrungen und eigene Felder');
    writeNews('Noch nicht fertig', publish: false);

    app(TenantManager::class)->runFor($this->starter, function () {
        $admin = newsAdmin();

        expect(app(PlatformNewsFeed::class)->unreadCount($admin))->toBe(1);

        Livewire::actingAs($admin, 'web')
            ->test(DashboardIndex::class)
            ->assertSee('Eine Neuigkeit');

        Livewire::actingAs($admin, 'web')
            ->test(NewsIndex::class)
            ->assertSee('Ehrungen und eigene Felder')
            ->assertSee('<strong>Ehrungen</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Noch nicht fertig')
            ->assertSee('neu');

        expect(app(PlatformNewsFeed::class)->unreadCount($admin))->toBe(0);

        Livewire::actingAs($admin, 'web')
            ->test(DashboardIndex::class)
            ->assertDontSee('Eine Neuigkeit');
    });
});

it('limits news to the packages they are meant for', function () {
    writeNews('Nur ab Verein', minPlan: Plan::Verein->value);

    app(TenantManager::class)->runFor($this->starter, function () {
        expect(app(PlatformNewsFeed::class)->news())->toHaveCount(0);
    });

    app(TenantManager::class)->runFor($this->verein, function () {
        expect(app(PlatformNewsFeed::class)->news()->pluck('title')->all())->toBe(['Nur ab Verein']);
    });
});

it('mails the contact address of the clubs once when publishing', function () {
    writeNews('Neue Funktion', minPlan: Plan::Verein->value, sendEmail: true);

    Mail::assertSent(PlatformNewsMail::class, 1);
    Mail::assertSent(PlatformNewsMail::class, fn (PlatformNewsMail $mail) => $mail->hasTo('verein@example.com'));

    $news = PlatformNews::query()->sole();

    expect($news->emailed_count)->toBe(1)
        ->and($news->emailed_at)->not->toBeNull();

    // Erneutes Speichern mailt nicht noch einmal.
    Livewire::actingAs($this->platformAdmin, 'platform')
        ->test(News::class)
        ->call('editNews', $news->id)
        ->set('sendEmail', true)
        ->call('publish');

    Mail::assertSent(PlatformNewsMail::class, 1);
});

it('is only for admins of a club', function () {
    writeNews('Neue Funktion');

    app(TenantManager::class)->runFor($this->starter, function () {
        $kassier = User::factory()->create();

        expect(app(PlatformNewsFeed::class)->unreadCount($kassier))->toBe(0);

        Livewire::actingAs($kassier, 'web')
            ->test(NewsIndex::class)
            ->assertNotFound();
    });
});

it('withdraws and deletes news', function () {
    writeNews('Neue Funktion');
    $news = PlatformNews::query()->sole();

    Livewire::actingAs($this->platformAdmin, 'platform')
        ->test(News::class)
        ->call('unpublish', $news->id)
        ->assertSee('Entwurf');

    app(TenantManager::class)->runFor($this->starter, function () {
        expect(app(PlatformNewsFeed::class)->news())->toHaveCount(0);
    });

    Livewire::actingAs($this->platformAdmin, 'platform')
        ->test(News::class)
        ->call('deleteNews', $news->id);

    expect(PlatformNews::count())->toBe(0);
});

describe('news shipped with an update', function () {
    it('imports each news file once as a draft', function () {
        File::put($this->newsDirectory.'/2026-11-09-online-anmeldung.md', "---\ntitle: Neu: Online-Anmeldung\nmin_plan: verein\n---\nNeue Mitglieder melden sich **online** an.\n");
        File::put($this->newsDirectory.'/kaputt.md', "Ohne Kopf\n");

        Livewire::actingAs($this->platformAdmin, 'platform')
            ->test(News::class)
            ->assertSet('importedDrafts', 1)
            ->assertSee('aus dem letzten Update als Entwurf übernommen')
            ->assertSee('Neu: Online-Anmeldung');

        $news = PlatformNews::sole();

        expect($news->isPublished())->toBeFalse()
            ->and($news->min_plan)->toBe(Plan::Verein)
            ->and($news->body)->toBe('Neue Mitglieder melden sich **online** an.');

        // Gelöschte Entwürfe kommen nicht wieder.
        $news->delete();

        expect(app(PlatformNewsDrafts::class)->sync())->toBe(0)
            ->and(PlatformNews::count())->toBe(0);
    });

    it('can mail an imported draft to the clubs when publishing', function () {
        File::put($this->newsDirectory.'/2026-11-09-neu.md', "---\ntitle: Etwas Neues\n---\nText\n");

        $this->artisan('platform-news:sync')->assertSuccessful();

        Livewire::actingAs($this->platformAdmin, 'platform')
            ->test(News::class)
            ->call('editNews', PlatformNews::sole()->id)
            ->set('sendEmail', true)
            ->call('publish');

        Mail::assertSent(PlatformNewsMail::class, 2);
        expect(PlatformNews::sole()->emailed_count)->toBe(2);
    });
});

<?php

use App\Enums\HomepageTemplate;
use App\Livewire\Homepage\Notices;
use App\Models\ActivityLog;
use App\Models\HomepagePage;
use App\Models\HomepageSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    HomepageSetting::current()->update(['enabled' => true]);

    $this->editor = User::factory()->create();
    $this->editor->assignRole(Role::findOrCreate('homepage', 'web'));
});

function activePopup(array $attributes = []): void
{
    HomepageSetting::current()->update(array_merge([
        'popup_enabled' => true,
        'popup_title' => 'Training fällt aus',
        'popup_html' => '<p>Diese Woche kein Training.</p>',
    ], $attributes));
}

describe('administration', function () {
    it('opens for homepage editors', function () {
        $this->actingAs($this->editor)->get(route('homepage-admin.notices'))->assertOk()->assertSee('Cookie-Hinweis');
    });

    it('saves the popup and shows it again after a change', function () {
        $version = HomepageSetting::current()->popup_version;

        $component = Livewire::actingAs($this->editor)
            ->test(Notices::class)
            ->set('popupEnabled', true)
            ->set('popupTitle', 'Wichtig')
            ->set('popupHtml', '<p>Hallo</p><script>x()</script>')
            ->set('popupButtonLabel', 'Zu den Terminen')
            ->set('popupButtonUrl', '/termine')
            ->set('popupStartsAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->set('popupEndsAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('popupFrequency', 'session')
            ->call('save')
            ->assertHasNoErrors();

        $settings = HomepageSetting::current();

        expect($settings->popup_html)->toBe('<p>Hallo</p>')
            ->and($settings->popupIsActive())->toBeTrue()
            ->and($settings->popup_frequency)->toBe('session')
            ->and($settings->popup_version)->toBe($version + 1)
            ->and(ActivityLog::where('action', 'homepage.notices_updated')->exists())->toBeTrue();

        // Ohne inhaltliche Änderung bleibt die Version gleich.
        $component->set('popupFrequency', 'once')->call('save');
        expect(HomepageSetting::current()->popup_version)->toBe($version + 1);

        $component->set('popupTitle', 'Ganz wichtig')->call('save');
        expect(HomepageSetting::current()->popup_version)->toBe($version + 2);
    });

    it('validates the popup', function () {
        Livewire::actingAs($this->editor)
            ->test(Notices::class)
            ->set('popupEnabled', true)
            ->set('popupButtonLabel', 'Los')
            ->set('popupButtonUrl', 'javascript:alert(1)')
            ->set('popupStartsAt', '2026-11-10T10:00')
            ->set('popupEndsAt', '2026-11-01T10:00')
            ->call('save')
            ->assertHasErrors(['popupTitle', 'popupButtonUrl', 'popupEndsAt']);
    });

    it('stores a popup image', function () {
        Livewire::actingAs($this->editor)
            ->test(Notices::class)
            ->set('popupImage', UploadedFile::fake()->image('hinweis.jpg', 1200, 800))
            ->assertHasNoErrors();

        Storage::disk('local')->assertExists(HomepageSetting::current()->popup_image_path);
    });

    it('saves the cookie notice', function () {
        $privacy = HomepagePage::factory()->create(['title' => 'Datenschutz', 'slug' => 'datenschutz']);

        Livewire::actingAs($this->editor)
            ->test(Notices::class)
            ->set('cookieBannerText', 'Nur notwendige Cookies.')
            ->set('cookieBannerPageID', $privacy->pageID)
            ->call('save')
            ->assertHasNoErrors();

        expect(HomepageSetting::current()->only(['cookie_banner_text', 'cookie_banner_page_id']))
            ->toBe(['cookie_banner_text' => 'Nur notwendige Cookies.', 'cookie_banner_page_id' => $privacy->pageID]);
    });
});

describe('homepage', function () {
    it('shows the popup on the home page of every template', function (HomepageTemplate $template) {
        HomepageSetting::current()->update(['template' => $template]);
        activePopup(['popup_button_label' => 'Mehr', 'popup_button_url' => '/termine', 'popup_frequency' => 'session']);
        $version = HomepageSetting::current()->popup_version;

        $this->get(route('homepage.home'))
            ->assertOk()
            ->assertSee('data-hp-popup="'.HomepageSetting::current()->homepageSettingID.'-'.$version.'"', false)
            ->assertSee('data-frequency="session"', false)
            ->assertSee('Training fällt aus')
            ->assertSee('Diese Woche kein Training.')
            ->assertSee(route('homepage.home').'/termine', false);
    })->with(HomepageTemplate::cases());

    it('shows the popup only on the home page', function () {
        activePopup();

        $this->get(route('homepage.posts.index'))->assertOk()->assertDontSee('data-hp-popup', false);
    });

    it('respects the time window and the switch', function () {
        activePopup(['popup_starts_at' => now()->addDay()]);
        $this->get(route('homepage.home'))->assertDontSee('data-hp-popup', false);

        activePopup(['popup_starts_at' => null, 'popup_ends_at' => now()->subMinute()]);
        $this->get(route('homepage.home'))->assertDontSee('data-hp-popup', false);

        activePopup(['popup_ends_at' => null, 'popup_enabled' => false]);
        $this->get(route('homepage.home'))->assertDontSee('data-hp-popup', false);
    });

    it('shows the cookie notice with the privacy link on every page', function () {
        HomepagePage::factory()->create(['title' => 'Datenschutz', 'slug' => 'datenschutz', 'in_menu' => false]);
        HomepageSetting::current()->update(['cookie_banner_page_id' => HomepagePage::sole()->pageID]);

        $this->get(route('homepage.posts.index'))
            ->assertOk()
            ->assertSee('data-hp-cookie', false)
            ->assertSee(HomepageSetting::DEFAULT_COOKIE_BANNER_TEXT)
            ->assertSee(route('homepage.page', 'datenschutz'));
    });

    it('hides the cookie notice when switched off', function () {
        HomepageSetting::current()->update(['cookie_banner_enabled' => false]);

        $this->get(route('homepage.home'))->assertOk()->assertDontSee('data-hp-cookie', false);
    });

    it('serves the popup image only while the popup is active', function () {
        Storage::disk('local')->put('homepage/popup/bild.jpg', 'jpeg');
        activePopup(['popup_image_path' => 'homepage/popup/bild.jpg']);

        $this->get(route('homepage.popup-image'))->assertOk();

        HomepageSetting::current()->update(['popup_enabled' => false]);

        $this->get(route('homepage.popup-image'))->assertNotFound();
    });
});

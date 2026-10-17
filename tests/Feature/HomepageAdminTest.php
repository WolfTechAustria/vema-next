<?php

use App\Enums\HomepageTemplate;
use App\Enums\PostStatus;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Homepage\Pages\Index as PagesIndex;
use App\Livewire\Homepage\Posts\Edit as PostsEdit;
use App\Livewire\Homepage\Posts\Index as PostsIndex;
use App\Livewire\Homepage\Settings;
use App\Models\ActivityLog;
use App\Models\HomepagePage;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\User;
use App\Services\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
});

function homepageEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('homepage', 'web'));

    return $user;
}

describe('access', function () {
    it('lets homepage editors and admins open the admin pages', function (string $routeName) {
        $this->actingAs(homepageEditor())->get(route($routeName))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route($routeName))->assertOk();
    })->with([
        'homepage-admin.posts.index',
        'homepage-admin.posts.create',
        'homepage-admin.pages.index',
        'homepage-admin.settings',
    ]);

    it('forbids staff without the homepage role', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('homepage-admin.posts.index'))
            ->assertForbidden();
    });

    it('sends guests to the login', function () {
        $this->get(route('homepage-admin.posts.index'))->assertRedirect(route('login'));
    });

    it('shows the homepage menu only to editors', function () {
        $this->actingAs(homepageEditor())->get(route('dashboard'))->assertSee(route('homepage-admin.posts.index'));
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertDontSee(route('homepage-admin.posts.index'));
    });

    it('lets admins grant the homepage role', function () {
        $user = User::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(UsersIndex::class)
            ->call('toggleHomepage', $user->id);

        expect($user->fresh()->hasRole('homepage'))->toBeTrue()
            ->and(ActivityLog::where('action', 'user.homepage_granted')->exists())->toBeTrue();
    });
});

describe('posts', function () {
    beforeEach(function () {
        $this->actingAs($this->editor = homepageEditor());
    });

    it('creates a post with sanitized text and gallery images', function () {
        Livewire::test(PostsEdit::class)
            ->set('title', 'Großes Sommerfest 2026')
            ->assertSet('slug', 'grosses-sommerfest-2026')
            ->set('bodyHtml', '<p>Danke <strong>allen</strong>!</p><script>alert(1)</script><img src=x onerror="alert(2)">')
            ->set('status', 'published')
            ->set('newImages', [UploadedFile::fake()->image('a.jpg', 2400, 1600), UploadedFile::fake()->image('b.png', 800, 600)])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $post = Post::sole();

        expect($post->body_html)->toBe('<p>Danke <strong>allen</strong>!</p>')
            ->and($post->status)->toBe(PostStatus::Published)
            ->and($post->published_at)->not->toBeNull()
            ->and($post->created_by)->toBe($this->editor->id)
            ->and($post->images)->toHaveCount(2);

        $large = $post->images->first();
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('local')->get($large->file_path));

        expect([$width, $height, $type])->toBe([1600, 1067, IMAGETYPE_JPEG]);
        Storage::disk('local')->assertExists($large->thumb_path);

        expect(ActivityLog::where('action', 'homepage.post_created')->exists())->toBeTrue()
            ->and(ActivityLog::where('action', 'homepage.post_images_added')->exists())->toBeTrue();
    });

    it('keeps slugs unique', function () {
        Post::factory()->create(['title' => 'Rückblick', 'slug' => 'rueckblick']);

        Livewire::test(PostsEdit::class)
            ->set('title', 'Rückblick')
            ->assertSet('slug', 'rueckblick-2')
            ->set('slug', 'rueckblick')
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);
    });

    it('publishes a draft and logs it', function () {
        $post = Post::factory()->draft()->create();

        Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('status', 'published')
            ->call('save')
            ->assertHasNoErrors();

        expect($post->fresh()->isPublic())->toBeTrue()
            ->and(ActivityLog::where('action', 'homepage.post_published')->exists())->toBeTrue();
    });

    it('adds, reorders and deletes images of an existing post', function () {
        $post = Post::factory()->create();

        $component = Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('newImages', [UploadedFile::fake()->image('eins.jpg'), UploadedFile::fake()->image('zwei.jpg')]);

        [$first, $second] = $post->images()->get()->all();

        $component->call('moveImage', $second->postImageID, 'up');

        expect($post->images()->first()->postImageID)->toBe($second->postImageID);

        $component->call('deleteImage', $first->postImageID);

        Storage::disk('local')->assertMissing($first->file_path);
        expect($post->images()->count())->toBe(1);
    });

    it('changes the cover image afterwards', function () {
        $post = Post::factory()->create();

        $component = Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('newImages', [UploadedFile::fake()->image('eins.jpg'), UploadedFile::fake()->image('zwei.jpg'), UploadedFile::fake()->image('drei.jpg')]);

        [$first, $second, $third] = $post->images()->get()->all();

        $component->call('makeCover', $third->postImageID)->assertHasNoErrors();

        expect($post->images()->pluck('postImageID')->all())->toBe([$third->postImageID, $first->postImageID, $second->postImageID])
            ->and($post->fresh()->coverImage->postImageID)->toBe($third->postImageID)
            ->and(ActivityLog::where('action', 'homepage.post_cover_changed')->exists())->toBeTrue();

        $component->set('newCover', UploadedFile::fake()->image('neu.jpg', 1200, 800))->assertHasNoErrors();

        $images = $post->images()->get();

        expect($images)->toHaveCount(4)
            ->and($images->first()->postImageID)->not->toBeIn([$first->postImageID, $second->postImageID, $third->postImageID])
            ->and($images->get(1)->postImageID)->toBe($third->postImageID);
    });

    it('cannot make a foreign image the cover', function () {
        $foreignImage = PostImage::factory()->create();

        Livewire::test(PostsEdit::class, ['post' => Post::factory()->create()])
            ->call('makeCover', $foreignImage->postImageID)
            ->assertNotFound();
    });

    it('rejects files that are not images', function () {
        Livewire::test(PostsEdit::class, ['post' => Post::factory()->create()])
            ->set('newImages', [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])
            ->assertHasErrors(['newImages.0']);
    });

    it('cannot touch images of another post', function () {
        $foreignImage = PostImage::factory()->create();

        Livewire::test(PostsEdit::class, ['post' => Post::factory()->create()])
            ->call('deleteImage', $foreignImage->postImageID)
            ->assertNotFound();
    });

    it('deletes a post together with its image files', function () {
        $post = Post::factory()->create();
        Storage::disk('local')->put('homepage/posts/x/a.jpg', 'x');
        Storage::disk('local')->put('homepage/posts/x/a_thumb.jpg', 'x');
        PostImage::factory()->for($post)->create(['file_path' => 'homepage/posts/x/a.jpg', 'thumb_path' => 'homepage/posts/x/a_thumb.jpg']);

        Livewire::test(PostsIndex::class)->call('deletePost', $post->postID);

        expect(Post::count())->toBe(0);
        Storage::disk('local')->assertMissing('homepage/posts/x/a.jpg');
    });

    it('shows drafts in the preview', function () {
        $post = Post::factory()->draft()->create(['title' => 'Geheimer Entwurf']);

        $this->get(route('homepage-admin.posts.preview', $post))
            ->assertOk()
            ->assertSee('Geheimer Entwurf')
            ->assertSee('Vorschau');
    });
});

describe('pages and settings', function () {
    beforeEach(function () {
        $this->actingAs(homepageEditor());
    });

    it('creates a page and refuses reserved addresses', function () {
        Livewire::test(PagesIndex::class)
            ->set('title', 'Impressum')
            ->set('bodyHtml', '<p>Verein XY</p>')
            ->set('published', true)
            ->set('inMenu', false)
            ->call('savePage')
            ->assertHasNoErrors();

        expect(HomepagePage::sole()->only(['slug', 'published', 'in_menu']))->toBe(['slug' => 'impressum', 'published' => true, 'in_menu' => false]);

        Livewire::test(PagesIndex::class)
            ->set('title', 'News')
            ->call('savePage')
            ->assertHasErrors(['slug' => 'not_in']);
    });

    it('saves the design settings', function () {
        Livewire::test(Settings::class)
            ->set('enabled', true)
            ->set('template', 'sportlich')
            ->set('primaryColor', '#FF0000')
            ->set('accentColor', '#1D3F94')
            ->set('heroTitle', 'Servus!')
            ->set('aboutHtml', '<p>Seit 1890</p><iframe src="https://evil.test"></iframe>')
            ->set('domain', 'https://www.Mein-Verein.at/')
            ->call('save')
            ->assertHasNoErrors();

        $settings = HomepageSetting::current();

        expect($settings->enabled)->toBeTrue()
            ->and($settings->template)->toBe(HomepageTemplate::Sportlich)
            ->and($settings->primary_color)->toBe('#ff0000')
            ->and($settings->accent_color)->toBe('#1d3f94')
            ->and($settings->about_html)->toBe('<p>Seit 1890</p>')
            ->and($settings->domain)->toBe('mein-verein.at')
            ->and(ActivityLog::where('action', 'homepage.settings_updated')->exists())->toBeTrue();
    });

    it('refuses the app domain as homepage domain', function () {
        Livewire::test(Settings::class)
            ->set('domain', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost.test')
            ->call('save')
            ->assertHasErrors('domain');
    });

    it('stores and removes the hero image', function () {
        $component = Livewire::test(Settings::class)
            ->set('heroImage', UploadedFile::fake()->image('titel.jpg', 3000, 1000))
            ->assertHasNoErrors();

        $path = HomepageSetting::current()->hero_image_path;
        Storage::disk('local')->assertExists($path);

        $component->call('removeHeroImage');

        Storage::disk('local')->assertMissing($path);
        expect(HomepageSetting::current()->hero_image_path)->toBeNull();
    });
});

it('removes scripts and event handlers from editor html', function () {
    $sanitizer = app(HtmlSanitizer::class);

    expect($sanitizer->sanitize('<p onclick="x()">Hi <a href="javascript:alert(1)">Link</a> <a href="https://verein.at">ok</a></p>'))
        ->toBe('<p>Hi <a rel="noopener noreferrer">Link</a> <a href="https://verein.at" rel="noopener noreferrer">ok</a></p>')
        ->and($sanitizer->sanitize('<p><br></p>'))->toBe('');
});

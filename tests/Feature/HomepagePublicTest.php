<?php

use App\Enums\HomepageTemplate;
use App\Models\BoardFunction;
use App\Models\HomepagePage;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\Post;
use App\Models\PostImage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Setting::current()->update(['name' => 'Musikverein Angerberg', 'email' => 'info@mv-angerberg.at']);
});

function enableHomepage(array $attributes = []): HomepageSetting
{
    $settings = HomepageSetting::current();
    $settings->update(array_merge(['enabled' => true], $attributes));

    return $settings;
}

function boardMember(string $function, string $firstName, bool $withPhoto = false): Member
{
    $member = Member::create(['gender' => 'm', 'name' => $firstName, 'surname' => 'Huber', 'active' => 1]);

    if ($withPhoto) {
        Storage::disk('local')->put('member-photos/'.$member->memberID.'/foto.jpg', 'jpeg');
        $member->update(['photo_path' => 'member-photos/'.$member->memberID.'/foto.jpg']);
    }

    MemberBoardFunctionAssignment::create([
        'memberID' => $member->memberID,
        'boardFunctionID' => BoardFunction::firstOrCreate(['name' => $function])->boardFunctionID,
        'date_from' => now()->subYear(),
    ]);

    return $member;
}

it('is hidden while switched off', function () {
    $this->get(route('homepage.home'))->assertNotFound();
});

it('can be previewed by homepage editors while switched off', function () {
    $editor = User::factory()->create();
    $editor->assignRole(Role::findOrCreate('homepage', 'web'));

    $this->actingAs($editor)->get(route('homepage.home'))->assertOk()->assertSee('Vorschau');
});

it('renders every template', function (HomepageTemplate $template) {
    enableHomepage(['template' => $template, 'hero_title' => 'Servus beim Musikverein', 'about_html' => '<p>Seit 1890</p>']);
    Post::factory()->create(['title' => 'Frühjahrskonzert']);
    boardMember('Obmann', 'Josef');
    HomepagePage::factory()->create(['title' => 'Geschichte', 'slug' => 'geschichte']);

    $this->get(route('homepage.home'))
        ->assertOk()
        ->assertSee('Servus beim Musikverein')
        ->assertSee('Frühjahrskonzert')
        ->assertSee('Geschichte')
        ->assertDontSee('Vorschau');

    $this->get(route('homepage.posts.index'))->assertOk()->assertSee('Frühjahrskonzert');
    $this->get(route('homepage.board'))->assertOk()->assertSee('Josef Huber')->assertSee('Obmann');
    $this->get(route('homepage.about'))->assertOk()->assertSee('Seit 1890');
    $this->get(route('homepage.contact'))->assertOk()->assertSee('info@mv-angerberg.at');
    $this->get(route('homepage.page', 'geschichte'))->assertOk();
})->with(HomepageTemplate::cases());

it('lists only published posts that are due', function () {
    enableHomepage();
    Post::factory()->create(['title' => 'Sichtbarer Beitrag']);
    $draft = Post::factory()->draft()->create(['title' => 'Entwurf XY']);
    $scheduled = Post::factory()->scheduled()->create(['title' => 'Geplant XY']);

    $this->get(route('homepage.posts.index'))
        ->assertSee('Sichtbarer Beitrag')
        ->assertDontSee('Entwurf XY')
        ->assertDontSee('Geplant XY');

    $this->get(route('homepage.posts.show', $draft->slug))->assertNotFound();
    $this->get(route('homepage.posts.show', $scheduled->slug))->assertNotFound();
});

it('sanitizes post html on output', function () {
    enableHomepage();
    $post = Post::factory()->create(['body_html' => '<p>Hallo</p><script>alert("x")</script>']);

    $this->get(route('homepage.posts.show', $post->slug))
        ->assertOk()
        ->assertSee('Hallo')
        ->assertDontSee('alert("x")', false);
});

it('serves images of published posts only', function () {
    enableHomepage();
    Storage::disk('local')->put('homepage/posts/1/a.jpg', 'gross');
    Storage::disk('local')->put('homepage/posts/1/a_thumb.jpg', 'klein');

    $public = PostImage::factory()->for(Post::factory())->create(['file_path' => 'homepage/posts/1/a.jpg', 'thumb_path' => 'homepage/posts/1/a_thumb.jpg']);
    $hidden = PostImage::factory()->for(Post::factory()->draft())->create(['file_path' => 'homepage/posts/1/a.jpg', 'thumb_path' => 'homepage/posts/1/a_thumb.jpg']);

    $response = $this->get(route('homepage.images.show', ['image' => $public->postImageID, 'size' => 'gross']))->assertOk();

    expect($response->streamedContent())->toBe('gross')
        ->and($response->headers->get('Cache-Control'))->toContain('public');

    $this->get(route('homepage.images.show', ['image' => $hidden->postImageID, 'size' => 'klein']))->assertNotFound();
});

it('hides unpublished pages and disabled sections', function () {
    enableHomepage(['show_board' => false, 'contact_visible' => false]);
    HomepagePage::factory()->draft()->create(['slug' => 'intern']);

    $this->get(route('homepage.page', 'intern'))->assertNotFound();
    $this->get(route('homepage.board'))->assertNotFound();
    $this->get(route('homepage.contact'))->assertNotFound();
    $this->get(route('homepage.about'))->assertNotFound();
});

it('shows board photos only when enabled and only for board members', function () {
    enableHomepage(['show_board_photos' => false]);
    $boardMember = boardMember('Kassier', 'Maria', withPhoto: true);
    $otherMember = Member::create(['gender' => 'w', 'name' => 'Eva', 'surname' => 'Gruber', 'active' => 1, 'photo_path' => 'member-photos/x/foto.jpg']);

    $this->get(route('homepage.board.photo', $boardMember->memberID))->assertNotFound();

    HomepageSetting::current()->update(['show_board_photos' => true]);

    $this->get(route('homepage.board.photo', $boardMember->memberID))->assertOk();
    $this->get(route('homepage.board.photo', $otherMember->memberID))->assertNotFound();
});

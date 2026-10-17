<?php

use App\Enums\HomepageSectionType;
use App\Enums\HomepageTemplate;
use App\Livewire\Homepage\Content;
use App\Livewire\Homepage\Pages\Index;
use App\Livewire\Homepage\Posts\Edit as PostsEdit;
use App\Livewire\Homepage\Sections;
use App\Models\ActivityLog;
use App\Models\BoardFunction;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\MemberBoardFunctionAssignment;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
});

function sectionsEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('homepage', 'web'));

    return $user;
}

function fakePdf(string $name = 'einladung.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%Testinhalt\n");
}

describe('section builder', function () {
    beforeEach(function () {
        $this->actingAs(sectionsEditor());
        $this->page = HomepagePage::factory()->create(['title' => 'Mitglied werden', 'slug' => 'mitglied-werden']);
    });

    it('opens for pages and for about', function () {
        $this->get(route('homepage-admin.pages.sections', $this->page))->assertOk();
        $this->get(route('homepage-admin.about'))->assertOk();
    });

    it('adds a text box section with info box and pdf button', function () {
        $component = Livewire::test(Sections::class, ['page' => $this->page])
            ->call('addSection', 'text_box');

        $section = HomepageSection::sole();

        $component
            ->set('heading', 'So einfach geht\'s')
            ->set('bodyHtml', '<ol><li>Vorbeischauen</li></ol><script>x()</script>')
            ->set('boxTitle', 'Mitgliedsbeitrag')
            ->set('boxValue', '€ 40,–')
            ->set('boxNote', 'pro Saison')
            ->set('buttonLabel', 'Beitrittserklärung (PDF)')
            ->set('file', fakePdf())
            ->set('altBackground', true)
            ->call('saveSection')
            ->assertHasNoErrors();

        $section->refresh();

        expect($section->type)->toBe(HomepageSectionType::TextBox)
            ->and($section->body_html)->toBe('<ol><li>Vorbeischauen</li></ol>')
            ->and($section->value('box_value'))->toBe('€ 40,–')
            ->and($section->alt_background)->toBeTrue()
            ->and(ActivityLog::where('action', 'homepage.section_updated')->exists())->toBeTrue();

        Storage::disk('local')->assertExists($section->file_path);
    });

    it('rejects fake pdfs and unsafe links', function () {
        Livewire::test(Sections::class, ['page' => $this->page])
            ->call('addSection', 'cta')
            ->set('buttonUrl', 'javascript:alert(1)')
            ->call('saveSection')
            ->assertHasErrors('buttonUrl')
            ->set('buttonUrl', '/kontakt')
            ->set('file', UploadedFile::fake()->createWithContent('fake.pdf', 'kein pdf'))
            ->call('saveSection')
            ->assertHasErrors('file');
    });

    it('stores images, reorders and deletes sections with their files', function () {
        $component = Livewire::test(Sections::class, ['page' => $this->page])
            ->call('addSection', 'text')
            ->call('cancelEdit')
            ->call('addSection', 'text_image')
            ->set('image', UploadedFile::fake()->image('stand.jpg', 2000, 1200))
            ->call('saveSection')
            ->assertHasNoErrors();

        [$text, $withImage] = HomepageSection::query()->ordered()->get()->all();
        $imagePath = $withImage->fresh()->image_path;
        Storage::disk('local')->assertExists($imagePath);

        $component->call('moveSection', $withImage->sectionID, 'up');
        expect(HomepageSection::query()->ordered()->first()->sectionID)->toBe($withImage->sectionID);

        $component->call('deleteSection', $withImage->sectionID);
        Storage::disk('local')->assertMissing($imagePath);
        expect(HomepageSection::count())->toBe(1);
    });

    it('cannot edit sections of another page', function () {
        $foreign = HomepageSection::factory()->create();

        Livewire::test(Sections::class, ['page' => $this->page])
            ->call('editSection', $foreign->sectionID)
            ->assertNotFound();
    });

    it('deletes sections together with the page', function () {
        HomepageSection::factory()->for($this->page, 'page')->create();

        Livewire::test(Index::class)->call('deletePage', $this->page->pageID);

        expect(HomepageSection::count())->toBe(0);
    });
});

describe('content settings', function () {
    beforeEach(function () {
        $this->actingAs(sectionsEditor());
    });

    it('saves tiles, stats, headings and footer', function () {
        Livewire::test(Content::class)
            ->set('heroEyebrow', 'Sportschießen seit 1991')
            ->set('heroButtonLabel', 'Probetraining')
            ->set('heroButtonUrl', '/training')
            ->set('tiles.0.title', 'Jeden Montag ab 18 Uhr')
            ->set('tiles.0.kicker', 'Training')
            ->set('tiles.0.url', '/training')
            ->set('stats.0.value', '1991')
            ->set('stats.0.label', 'gegründet')
            ->set('pageHeadings.about', 'Klein, aber fein – seit 1991')
            ->set('pageHeadings.news_menu', 'Aktuelles')
            ->set('footerInfoTitle', 'Training')
            ->set('footerInfoText', 'Jeden Montag ab 18:00 Uhr')
            ->set('showMemberAreaLink', true)
            ->call('save')
            ->assertHasNoErrors();

        $settings = HomepageSetting::current();

        expect($settings->tiles)->toHaveCount(1)
            ->and($settings->tiles[0]['title'])->toBe('Jeden Montag ab 18 Uhr')
            ->and($settings->stats)->toBe([['value' => '1991', 'label' => 'gegründet']])
            ->and($settings->pageHeading('about'))->toBe('Klein, aber fein – seit 1991')
            ->and($settings->newsLabel())->toBe('Aktuelles')
            ->and($settings->show_member_area_link)->toBeTrue()
            ->and(ActivityLog::where('action', 'homepage.content_updated')->exists())->toBeTrue();
    });

    it('requires a title for an enabled announcement and stores its files', function () {
        $component = Livewire::test(Content::class)
            ->set('announcementEnabled', true)
            ->call('save')
            ->assertHasErrors('announcementTitle');

        $component
            ->set('announcementTitle', 'Dorfmeisterschaft')
            ->set('announcementImage', UploadedFile::fake()->image('banner.png', 1600, 900))
            ->set('announcementFile', fakePdf())
            ->call('save')
            ->assertHasNoErrors();

        $settings = HomepageSetting::current();

        Storage::disk('local')->assertExists($settings->announcement_image_path);
        Storage::disk('local')->assertExists($settings->announcement_file_path);
    });
});

describe('post downloads', function () {
    it('uploads, renames and deletes pdf downloads', function () {
        $this->actingAs(sectionsEditor());
        $post = Post::factory()->create();

        $component = Livewire::test(PostsEdit::class, ['post' => $post])
            ->set('newAttachments', [fakePdf('ergebnisliste.pdf')])
            ->assertHasNoErrors();

        $attachment = $post->attachments()->sole();
        expect($attachment->label)->toBe('ergebnisliste');

        $component->set('attachmentLabels.'.$attachment->postAttachmentID, 'Ergebnisliste 2022')->call('save');
        expect($attachment->fresh()->label)->toBe('Ergebnisliste 2022');

        $component->call('deleteAttachment', $attachment->postAttachmentID);
        Storage::disk('local')->assertMissing($attachment->file_path);
    });

    it('serves downloads of published posts only', function () {
        HomepageSetting::current()->update(['enabled' => true]);
        Storage::disk('local')->put('homepage/posts/1/a.pdf', '%PDF-1.4');

        $public = PostAttachment::factory()->for(Post::factory())->create(['file_path' => 'homepage/posts/1/a.pdf']);
        $hidden = PostAttachment::factory()->for(Post::factory()->draft())->create(['file_path' => 'homepage/posts/1/a.pdf']);

        $this->get(route('homepage.attachments.show', $public->postAttachmentID))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('homepage.attachments.show', $hidden->postAttachmentID))->assertNotFound();

        $this->get(route('homepage.posts.show', $public->post->slug))->assertSee('Downloads')->assertSee('Ergebnisliste');
    });
});

describe('verein template', function () {
    beforeEach(function () {
        HomepageSetting::current()->update([
            'enabled' => true,
            'template' => HomepageTemplate::Verein,
            'hero_title' => 'Herzlich willkommen',
            'hero_eyebrow' => 'Sportschießen seit 1991',
            'announcement_enabled' => true,
            'announcement_badge' => 'Einladung',
            'announcement_title' => 'Dorfmeisterschaft',
            'announcement_link_url' => 'https://example.test/einladung',
            'tiles' => [['kicker' => 'Training', 'title' => 'Jeden Montag ab 18 Uhr', 'text' => 'Acht Stände', 'url' => '/training', 'link_label' => 'Mehr zum Training']],
            'stats' => [['value' => '1991', 'label' => 'gegründet']],
            'page_headings' => ['about' => 'Klein, aber fein', 'news_menu' => 'Aktuelles'],
            'footer_info_title' => 'Training',
            'footer_info_text' => 'Jeden Montag ab 18:00 Uhr',
        ]);
    });

    it('renders all home sections', function () {
        $this->get(route('homepage.home'))
            ->assertOk()
            ->assertSeeInOrder(['Sportschießen seit 1991', 'Herzlich willkommen', 'Dorfmeisterschaft', 'Jeden Montag ab 18 Uhr', 'Aktuelles', '1991'])
            ->assertSee('Mehr zum Training')
            ->assertSee('Jeden Montag ab 18:00 Uhr');
    });

    it('renders about with heading and sections incl. board table', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Wolfgang', 'surname' => 'Oberhauser', 'active' => 1]);
        MemberBoardFunctionAssignment::create([
            'memberID' => $member->memberID,
            'boardFunctionID' => BoardFunction::firstOrCreate(['name' => 'Oberschützenmeister'])->boardFunctionID,
            'date_from' => now()->subYear(),
        ]);

        HomepageSection::factory()->about()->create(['type' => HomepageSectionType::Text, 'heading' => 'Die Anfänge', 'sort' => 1]);
        HomepageSection::factory()->about()->create(['type' => HomepageSectionType::Board, 'heading' => 'Vorstand', 'sort' => 2]);

        $this->get(route('homepage.about'))
            ->assertOk()
            ->assertSeeInOrder(['Über uns', 'Klein, aber fein', 'Die Anfänge', 'Oberschützenmeister', 'Wolfgang Oberhauser']);
    });

    it('renders custom pages with subtitle and every section type', function (HomepageTemplate $template) {
        HomepageSetting::current()->update(['template' => $template]);
        $page = HomepagePage::factory()->create(['title' => 'Training', 'slug' => 'training', 'subtitle' => 'Jeden Montag ab 18 Uhr']);

        foreach (HomepageSectionType::cases() as $index => $type) {
            HomepageSection::factory()->for($page, 'page')->create([
                'type' => $type,
                'heading' => 'Abschnitt '.$type->label(),
                'sort' => $index,
                'data' => ['items' => [['value' => '8', 'title' => 'Stände', 'text' => 'elektronisch']], 'box_value' => '€ 40,–', 'button_label' => 'Los', 'button_url' => '/kontakt'],
            ]);
        }

        $response = $this->get(route('homepage.page', 'training'))->assertOk()->assertSee('Jeden Montag ab 18 Uhr');

        foreach (HomepageSectionType::cases() as $type) {
            if ($type !== HomepageSectionType::ImagePair) {
                $response->assertSee('Abschnitt '.$type->label());
            }
        }
    })->with(HomepageTemplate::cases());

    it('filters news by year', function () {
        Post::factory()->create(['title' => 'Bericht 2022', 'published_at' => '2022-11-20 10:00:00']);
        Post::factory()->create(['title' => 'Bericht 2023', 'published_at' => '2023-02-10 10:00:00']);

        $this->get(route('homepage.posts.index', ['jahr' => '2022']))
            ->assertOk()
            ->assertSee('Bericht 2022')
            ->assertDontSee('Bericht 2023');
    });

    it('serves section media of published pages only', function () {
        Storage::disk('local')->put('homepage/sections/1/a.jpg', 'jpeg');

        $public = HomepageSection::factory()->create(['image_path' => 'homepage/sections/1/a.jpg']);
        $hidden = HomepageSection::factory()->for(HomepagePage::factory()->draft(), 'page')->create(['image_path' => 'homepage/sections/1/a.jpg']);

        $this->get(route('homepage.sections.media', ['section' => $public->sectionID, 'kind' => 'bild']))->assertOk();
        $this->get(route('homepage.sections.media', ['section' => $hidden->sectionID, 'kind' => 'bild']))->assertNotFound();
    });
});

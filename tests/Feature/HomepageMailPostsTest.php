<?php

use App\Enums\PostStatus;
use App\Livewire\Homepage\MailPosts;
use App\Mail\MailPostReceivedMail;
use App\Models\ActivityLog;
use App\Models\HomepageSetting;
use App\Models\Post;
use App\Models\User;
use App\Services\MailPosts\IncomingMail;
use App\Services\MailPosts\IncomingMailAttachment;
use App\Services\MailPosts\MailPostImporter;
use App\Services\MailPosts\MailPostInbox;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Postfach-Ersatz für Tests: liefert vorgegebene Mails und merkt sich, was verarbeitet wurde.
 */
class FakeMailPostInbox implements MailPostInbox
{
    /** @var list<IncomingMail> */
    public array $mails = [];

    /** @var list<string> */
    public array $processed = [];

    public function unread(HomepageSetting $settings): iterable
    {
        return $this->mails;
    }

    public function markProcessed(HomepageSetting $settings, IncomingMail $mail): void
    {
        $this->processed[] = $mail->messageId;
    }

    public function test(HomepageSetting $settings): int
    {
        return count($this->mails);
    }

    public function disconnect(): void {}
}

function mailPostJpeg(): string
{
    $image = imagecreatetruecolor(60, 40);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function incomingMail(array $overrides = []): IncomingMail
{
    return new IncomingMail(...array_merge([
        'messageId' => 'abc-'.uniqid().'@mail.test',
        'fromAddress' => 'pressewart@verein.test',
        'fromName' => 'Paula Presse',
        'subject' => 'AW: Fwd: Sommerfest 2026',
        'html' => null,
        'text' => "Ein toller Tag!\nMit vielen Gästen.\n\n- Schießen\n- Grillen\n\nFotos: https://example.test/album\n\n-- \nPaula Presse\nPressewart",
        'attachments' => [],
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();

    $this->inbox = new FakeMailPostInbox;
    $this->app->instance(MailPostInbox::class, $this->inbox);

    HomepageSetting::current()->update([
        'mail_posts_enabled' => true,
        'mail_posts_host' => 'imap.verein.test',
        'mail_posts_username' => 'homepage@verein.test',
        'mail_posts_password' => 'geheim',
        'mail_posts_allowed_senders' => "trainer@verein.test\nkein-mail",
    ]);

    $this->editor = User::factory()->create(['email' => 'Pressewart@Verein.test']);
    $this->editor->assignRole(Role::findOrCreate('homepage', 'web'));
});

describe('import', function () {
    it('turns a mail of an editor into a draft with gallery and downloads', function () {
        $this->inbox->mails[] = incomingMail([
            'messageId' => 'sommerfest@mail.test',
            'attachments' => [
                new IncomingMailAttachment('titel.jpg', 'image/jpeg', mailPostJpeg()),
                new IncomingMailAttachment('zweites.jpg', 'image/jpeg', mailPostJpeg()),
                new IncomingMailAttachment('ergebnisse.pdf', 'application/pdf', "%PDF-1.4\n%Test\n"),
                new IncomingMailAttachment('virus.exe', 'application/octet-stream', 'MZ'),
            ],
        ]);

        $result = app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect($result)->toBe(['created' => 1, 'rejected' => 0, 'duplicates' => 0, 'failed' => 0]);

        $post = Post::sole();

        expect($post->title)->toBe('Sommerfest 2026')
            ->and($post->status)->toBe(PostStatus::Draft)
            ->and($post->isPublic())->toBeFalse()
            ->and($post->created_by)->toBe($this->editor->id)
            ->and($post->submitted_by_email)->toBe('pressewart@verein.test')
            ->and($post->mail_message_id)->toBe('sommerfest@mail.test')
            ->and($post->body_html)->toBe('<p>Ein toller Tag!<br />Mit vielen Gästen.</p><ul><li>Schießen</li><li>Grillen</li></ul><p>Fotos: <a href="https://example.test/album" rel="noopener noreferrer">https://example.test/album</a></p>')
            ->and($post->images)->toHaveCount(2)
            ->and($post->attachments()->sole()->label)->toBe('ergebnisse')
            ->and($this->inbox->processed)->toBe(['sommerfest@mail.test'])
            ->and(ActivityLog::where('action', 'homepage.post_received_by_mail')->exists())->toBeTrue()
            ->and(HomepageSetting::current()->mail_posts_checked_at)->not->toBeNull();

        Mail::assertSent(MailPostReceivedMail::class, fn ($mail) => $mail->hasTo('pressewart@verein.test') && $mail->post->is($post));
    });

    it('accepts addresses from the allow list', function () {
        $this->inbox->mails[] = incomingMail(['fromAddress' => 'trainer@verein.test', 'subject' => 'Trainingsbericht']);

        app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect(Post::sole()->only(['title', 'created_by']))->toBe(['title' => 'Trainingsbericht', 'created_by' => null]);
    });

    it('ignores unknown senders and editors without homepage rights', function () {
        User::factory()->create(['email' => 'mitarbeiter@verein.test']);

        $this->inbox->mails[] = incomingMail(['fromAddress' => 'spam@fremd.test']);
        $this->inbox->mails[] = incomingMail(['fromAddress' => 'mitarbeiter@verein.test']);

        $result = app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect($result['rejected'])->toBe(2)
            ->and(Post::count())->toBe(0)
            ->and($this->inbox->processed)->toHaveCount(2);

        Mail::assertNothingSent();
    });

    it('ignores disabled users', function () {
        $this->editor->update(['enabled' => false]);
        $this->inbox->mails[] = incomingMail();

        expect(app(MailPostImporter::class)->fetch(HomepageSetting::current())['rejected'])->toBe(1);
    });

    it('does not import the same message twice', function () {
        $this->inbox->mails[] = incomingMail(['messageId' => 'gleich@mail.test']);
        app(MailPostImporter::class)->fetch(HomepageSetting::current());

        $result = app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect($result['duplicates'])->toBe(1)->and(Post::count())->toBe(1);
    });

    it('cleans html mails and drops quoted replies', function () {
        $this->inbox->mails[] = incomingMail([
            'text' => null,
            'html' => '<div>Hallo <b>alle</b></div><div><br></div><div>Zweiter Absatz</div><script>alert(1)</script><div class="gmail_quote">Am Montag schrieb jemand …</div>',
        ]);

        app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect(Post::sole()->body_html)->toBe('<p>Hallo <b>alle</b></p><p>Zweiter Absatz</p>');
    });

    it('cuts quoted replies from plain text', function () {
        expect(app(MailPostImporter::class)->textToHtml("Neuer Text\n\nAm 12.10.2026 um 10:00 schrieb Max:\n> alter Text"))
            ->toBe('<p>Neuer Text</p>');
    });

    it('uses a fallback title for an empty subject', function () {
        $this->inbox->mails[] = incomingMail(['subject' => 'Re: ']);

        app(MailPostImporter::class)->fetch(HomepageSetting::current());

        expect(Post::sole()->title)->toBe('Beitrag vom '.now()->format('d.m.Y'));
    });
});

describe('command', function () {
    it('fetches the mailbox', function () {
        $this->inbox->mails[] = incomingMail();

        $this->artisan('homepage:fetch-mail-posts')
            ->expectsOutputToContain('1 neue Beiträge')
            ->assertSuccessful();

        expect(Post::count())->toBe(1);
    });

    it('does nothing when switched off', function () {
        HomepageSetting::current()->update(['mail_posts_enabled' => false]);
        $this->inbox->mails[] = incomingMail();

        $this->artisan('homepage:fetch-mail-posts')->assertSuccessful();

        expect(Post::count())->toBe(0);
    });

    it('is scheduled every five minutes for every club', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'tenants:run') && str_contains($event->command, 'homepage:fetch-mail-posts'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('*/5 * * * *');
    });
});

describe('administration', function () {
    it('lets admins store the mailbox with an encrypted password', function () {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(MailPosts::class)
            ->set('host', 'imap.neu.test')
            ->set('username', 'beitraege@verein.test')
            ->set('password', 'neues-geheimnis')
            ->set('allowedSenders', "a@verein.test\nb@verein.test")
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $settings = HomepageSetting::current();
        $raw = DB::table('tb_homepage_settings')->value('mail_posts_password');

        expect($settings->mail_posts_password)->toBe('neues-geheimnis')
            ->and($raw)->not->toContain('neues-geheimnis')
            ->and($settings->allowedMailPostSenders())->toBe(['a@verein.test', 'b@verein.test'])
            ->and(ActivityLog::where('action', 'homepage.mail_posts_updated')->exists())->toBeTrue();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(MailPosts::class)
            ->set('host', 'imap.neu.test')
            ->call('save');

        expect(HomepageSetting::current()->mail_posts_password)->toBe('neues-geheimnis');
    });

    it('shows homepage editors the instructions but not the credentials', function () {
        $this->actingAs($this->editor)
            ->get(route('homepage-admin.mail-posts'))
            ->assertOk()
            ->assertSee('So geht')
            ->assertDontSee('IMAP-Server');

        Livewire::actingAs($this->editor)
            ->test(MailPosts::class)
            ->call('save')
            ->assertForbidden();
    });

    it('fetches on demand', function () {
        $this->inbox->mails[] = incomingMail();

        Livewire::actingAs($this->editor)
            ->test(MailPosts::class)
            ->call('fetchNow')
            ->assertSee('1 neue(r) Beitrag');

        expect(Post::count())->toBe(1);
    });

    it('marks posts that came in by mail', function () {
        Post::factory()->draft()->create(['title' => 'Per Mail', 'submitted_by_email' => 'trainer@verein.test']);

        $this->actingAs($this->editor)
            ->get(route('homepage-admin.posts.index'))
            ->assertSee('per E-Mail von trainer@verein.test');
    });
});

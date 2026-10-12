<?php

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Circulars\Index as CircularsIndex;
use App\Livewire\Members\Create as MembersCreate;
use App\Livewire\Members\Show as MembersShow;
use App\Mail\MemberWelcomeMail;
use App\Mail\StaffPasswordResetMail;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Setting::current()->update(['name' => 'Schützengilde Musterdorf']);
    $this->actingAs(User::factory()->admin()->create());
});

function createMemberViaForm(array $emails, bool $sendWelcomeMail = true): Member
{
    Livewire::test(MembersCreate::class)
        ->set('gender', 'w')
        ->set('name', 'Anna')
        ->set('surname', 'Neumitglied')
        ->set('emails', array_map(fn (string $email) => ['email' => $email], $emails))
        ->set('sendWelcomeMail', $sendWelcomeMail)
        ->call('save')
        ->assertHasNoErrors();

    return Member::query()->where('surname', 'Neumitglied')->sole();
}

describe('new members', function () {
    it('sends the welcome mail when a member is created', function () {
        $member = createMemberViaForm(['anna@example.com']);

        Mail::assertSent(MemberWelcomeMail::class, fn (MemberWelcomeMail $mail) => $mail->hasTo('anna@example.com')
            && $mail->subjectLine === 'Willkommen bei Schützengilde Musterdorf');

        expect(ActivityLog::query()->where('action', 'member.welcome_sent')->where('subject_id', $member->memberID)->exists())->toBeTrue();
    });

    it('does not send the welcome mail when the checkbox is cleared', function () {
        createMemberViaForm(['anna@example.com'], sendWelcomeMail: false);

        Mail::assertNothingSent();
    });

    it('creates members without e-mail address without sending anything', function () {
        createMemberViaForm([]);

        Mail::assertNothingSent();
    });

    it('resends the welcome mail from the member page', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        MemberEmail::create(['memberID' => $member->memberID, 'email' => 'max@example.com']);
        MemberEmail::create(['memberID' => $member->memberID, 'email' => 'familie@example.com']);

        Livewire::test(MembersShow::class, ['member' => $member])
            ->assertSee('Willkommens-E-Mail senden')
            ->call('sendWelcomeMail')
            ->assertHasNoErrors();

        Mail::assertSent(MemberWelcomeMail::class, fn (MemberWelcomeMail $mail) => $mail->hasTo('max@example.com') && $mail->hasTo('familie@example.com'));
    });

    it('hides the resend button for members without e-mail address', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Ohne-Mail', 'active' => 1]);

        Livewire::test(MembersShow::class, ['member' => $member])
            ->assertDontSee('Willkommens-E-Mail senden');
    });
});

describe('mail content', function () {
    function renderedWelcomeMail(): string
    {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        MemberEmail::create(['memberID' => $member->memberID, 'email' => 'max@example.com']);

        Livewire::test(MembersShow::class, ['member' => $member])->call('sendWelcomeMail');

        $html = null;
        Mail::assertSent(MemberWelcomeMail::class, function (MemberWelcomeMail $mail) use (&$html) {
            $html = $mail->render();

            return true;
        });

        return $html;
    }

    it('uses the editable template text and links to the member portal', function () {
        Template::query()->where('key', Template::MEMBER_WELCOME)->update([
            'subject' => 'Servus {{first_name}}!',
            'body_html' => '<p>Willkommen im Verein, {{salutation}} {{full_name}}.</p>',
        ]);

        $html = renderedWelcomeMail();

        Mail::assertSent(MemberWelcomeMail::class, fn (MemberWelcomeMail $mail) => $mail->subjectLine === 'Servus Max!');

        expect($html)->toContain('Willkommen im Verein, lieber Max Muster.')
            ->and($html)->toContain('Zum Mitgliederbereich')
            ->and($html)->toContain(route('member.login'))
            ->and($html)->not->toContain('WhatsApp');
    });

    it('adds the WhatsApp community button when a link is stored', function () {
        Setting::current()->update(['whatsapp_community_url' => 'https://chat.whatsapp.com/AbCdEf123']);

        expect(renderedWelcomeMail())->toContain('WhatsApp-Community beitreten')
            ->and(renderedWelcomeMail())->toContain('https://chat.whatsapp.com/AbCdEf123');
    });

    it('falls back to the built-in text when the template is missing', function () {
        Template::query()->where('key', Template::MEMBER_WELCOME)->delete();

        expect(renderedWelcomeMail())->toContain('herzlich willkommen bei Schützengilde Musterdorf');
    });
});

describe('new officials', function () {
    beforeEach(function () {
        $this->mock(ImapSentMailService::class)->shouldReceive('append');
    });

    it('welcomes new officials with their login data and a link to the login page', function () {
        $member = Member::create(['gender' => 'w', 'name' => 'Maria', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(UsersIndex::class)
            ->call('selectMember', $member->memberID)
            ->set('newUsername', 'mariamuster')
            ->set('newEmail', 'maria@example.com')
            ->call('createUser')
            ->assertHasNoErrors();

        Mail::assertSent(StaffPasswordResetMail::class, function (StaffPasswordResetMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('maria@example.com')
                && $mail->hasSubject('Dein Zugang zur Vereinsverwaltung von Schützengilde Musterdorf')
                && str_contains($html, 'Hallo Maria,')
                && str_contains($html, 'mariamuster')
                && str_contains($html, 'Passwort festlegen')
                && str_contains($html, route('login'));
        });
    });

    it('keeps the plain password reset mail for existing accounts', function () {
        $user = User::factory()->create();

        Livewire::test(UsersIndex::class)->call('resendInvite', $user->id);

        Mail::assertSent(StaffPasswordResetMail::class, fn (StaffPasswordResetMail $mail) => $mail->hasSubject('Passwort zurücksetzen'));
    });
});

describe('templates and settings', function () {
    it('keeps the welcome templates out of the circular template list and PDF preview', function () {
        Livewire::test(CircularsIndex::class)
            ->assertDontSee('Willkommen – neues Mitglied');

        $template = Template::query()->where('key', Template::MEMBER_WELCOME)->sole();

        $this->get(route('templates.preview', $template))->assertNotFound();
    });

    it('stores the WhatsApp community link', function () {
        Livewire::test(SettingsIndex::class)
            ->set('whatsapp_community_url', 'http://chat.whatsapp.com/unsicher')
            ->call('save')
            ->assertHasErrors('whatsapp_community_url')
            ->set('whatsapp_community_url', 'https://chat.whatsapp.com/AbCdEf123')
            ->call('save')
            ->assertHasNoErrors();

        expect(Setting::current()->fresh()->whatsapp_community_url)->toBe('https://chat.whatsapp.com/AbCdEf123');
    });
});

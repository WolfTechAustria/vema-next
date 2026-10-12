<?php

use App\Livewire\Members\Show as MembersShow;
use App\Mail\CircularMail;
use App\Models\Circular;
use App\Models\CircularRecipient;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\User;
use App\Services\ImapSentMailService;
use App\Services\TemplateRendererService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Mehrere Mitglieder mit derselben E-Mail-Adresse (z. B. Familien) sollen
 * ein Rundschreiben nur einmal bekommen — mit Anrede für jede Person.
 */

beforeEach(function () {
    Mail::fake();
    $this->mock(ImapSentMailService::class)->shouldReceive('append');
    $this->actingAs(User::factory()->admin()->create());

    $this->circular = Circular::create([
        'title' => 'Einladung JHV',
        'subject' => 'Jahreshauptversammlung',
        'body_html' => '<p>{{salutation}} {{first_name}},</p><p>wir laden euch herzlich ein.</p>',
        'status' => 'draft',
    ]);
});

function circularMember(string $firstName, string $gender, string $surname = 'Huber'): Member
{
    return Member::create(['gender' => $gender, 'name' => $firstName, 'surname' => $surname, 'active' => 1]);
}

function addCircularRecipient(Circular $circular, Member|ExternalContact $person, string $email): CircularRecipient
{
    return CircularRecipient::create([
        'circularID' => $circular->circularID,
        'memberID' => $person instanceof Member ? $person->memberID : null,
        'externalContactID' => $person instanceof ExternalContact ? $person->externalContactID : null,
        'delivery_method' => 'email',
        'email' => $email,
    ]);
}

describe('greeting', function () {
    it('addresses every person of a shared address individually', function () {
        $body = app(TemplateRendererService::class)->circularForRecipients(
            '<p>{{salutation}} {{first_name}},</p><p>{{full_name}} · {{first_name}} · {{last_name}}</p>',
            [circularMember('Anna', 'w'), circularMember('Max', 'm'), circularMember('Lisa', 'w', 'Berger')],
        );

        expect($body)->toContain('<p>Liebe Anna, lieber Max und liebe Lisa,</p>')
            ->and($body)->toContain('Anna Huber, Max Huber und Lisa Berger · Anna, Max und Lisa · Huber und Berger');
    });

    it('keeps the single greeting for one person', function () {
        $body = app(TemplateRendererService::class)->circularForRecipients(
            '<p>{{salutation}} {{first_name}},</p>',
            [circularMember('Max', 'm')],
        );

        expect($body)->toBe('<p>Lieber Max,</p>');
    });
});

describe('sending', function () {
    it('sends one mail per address and marks every recipient as sent', function () {
        $anna = addCircularRecipient($this->circular, circularMember('Anna', 'w'), 'familie.huber@example.com');
        $max = addCircularRecipient($this->circular, circularMember('Max', 'm'), 'Familie.Huber@example.com ');
        $eva = addCircularRecipient($this->circular, circularMember('Eva', 'w', 'Berger'), 'eva@example.com');

        $this->post(route('circulars.send-emails', $this->circular))
            ->assertSessionHas('success', 'Rundschreiben wurde an 3 Empfänger per E-Mail versendet (2 E-Mails, gemeinsame Adressen zusammengefasst).');

        Mail::assertSentCount(2);

        Mail::assertSent(CircularMail::class, fn (CircularMail $mail) => $mail->hasTo('familie.huber@example.com')
            && str_contains($mail->body, 'Liebe Anna und lieber Max,'));

        Mail::assertSent(CircularMail::class, fn (CircularMail $mail) => $mail->hasTo('eva@example.com')
            && str_contains($mail->body, 'Liebe Eva,'));

        expect(CircularRecipient::query()->whereNull('sent_at')->count())->toBe(0)
            ->and($this->circular->fresh()->status)->toBe('sent');
    });

    it('also merges external contacts sharing the address', function () {
        addCircularRecipient($this->circular, circularMember('Anna', 'w'), 'gemeinsam@example.com');
        $contact = ExternalContact::create(['name' => 'Karl', 'surname' => 'Extern', 'email' => 'gemeinsam@example.com', 'active' => true]);
        addCircularRecipient($this->circular, $contact, 'gemeinsam@example.com');

        $this->post(route('circulars.send-emails', $this->circular));

        Mail::assertSentCount(1);
    });

    it('sends the test mail only to the chosen person', function () {
        $anna = addCircularRecipient($this->circular, circularMember('Anna', 'w'), 'familie@example.com');
        addCircularRecipient($this->circular, circularMember('Max', 'm'), 'familie@example.com');

        $this->post(route('circulars.test-mail', [$this->circular, $anna]));

        Mail::assertSent(CircularMail::class, fn (CircularMail $mail) => str_contains($mail->body, 'Liebe Anna,')
            && ! str_contains($mail->body, 'Max'));
        expect(CircularRecipient::query()->whereNotNull('sent_at')->count())->toBe(0);
    });
});

describe('previews', function () {
    it('shows the shared mail on the member page', function () {
        $annaMember = circularMember('Anna', 'w');
        $anna = addCircularRecipient($this->circular, $annaMember, 'familie@example.com');
        addCircularRecipient($this->circular, circularMember('Max', 'm'), 'familie@example.com');
        CircularRecipient::query()->update(['sent_at' => now()]);

        Livewire::test(MembersShow::class, ['member' => $annaMember])
            ->call('showCircularEmail', $anna->recipientID)
            ->assertSee('Liebe Anna und lieber Max,');
    });

    it('shows the shared mail in the circular e-mail preview', function () {
        $anna = addCircularRecipient($this->circular, circularMember('Anna', 'w'), 'familie@example.com');
        addCircularRecipient($this->circular, circularMember('Max', 'm'), 'familie@example.com');

        $this->get(route('circulars.email-preview', [$this->circular, $anna]))
            ->assertOk()
            ->assertSee('Liebe Anna und lieber Max,', false);
    });
});

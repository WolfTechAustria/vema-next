<?php

use App\Mail\BirthdayListMail;
use App\Models\Circular;
use App\Models\CircularRecipient;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\BirthdayRecipientService;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * Mails an mehrere Personen dürfen die Empfänger nicht gegenseitig
 * offenlegen: Rundschreiben gehen einzeln raus, Sammelmails per BCC.
 */

beforeEach(function () {
    $this->mock(ImapSentMailService::class)->shouldReceive('append');

    Setting::current()->update([
        'name' => 'Schützengilde Angerberg',
        'mail_from_address' => 'office@sg-angerberg.at',
    ]);
});

/**
 * @return list<Email>
 */
function sentMessages(): array
{
    return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()
        ->map(fn ($sentMessage) => $sentMessage->getOriginalMessage())
        ->values()
        ->all();
}

/**
 * @param  list<Address>  $addresses
 * @return list<string>
 */
function addressesOf(array $addresses): array
{
    return array_map(fn (Address $address) => $address->getAddress(), $addresses);
}

it('sends every circular recipient a separate mail', function () {
    $circular = Circular::create([
        'title' => 'Jahreshauptversammlung',
        'subject' => 'Einladung',
        'body_html' => '<p>Hallo</p>',
        'status' => 'draft',
    ]);

    foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
        $contact = ExternalContact::create(['name' => 'Kontakt', 'surname' => $email, 'email' => $email]);

        CircularRecipient::create([
            'circularID' => $circular->circularID,
            'externalContactID' => $contact->externalContactID,
            'delivery_method' => 'email',
            'email' => $email,
        ]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('circulars.send-emails', $circular))
        ->assertRedirect();

    $messages = sentMessages();

    expect($messages)->toHaveCount(3)
        ->and(array_map(fn ($message) => addressesOf($message->getTo()), $messages))
        ->toBe([['a@example.com'], ['b@example.com'], ['c@example.com']]);

    foreach ($messages as $message) {
        expect($message->getCc())->toBe([])
            ->and($message->getBcc())->toBe([]);
    }
});

it('sends birthday reminders to the board in bcc', function () {
    $this->mock(BirthdayRecipientService::class)
        ->shouldReceive('resolveEmails')
        ->andReturn(collect(['obmann@example.com', 'kassier@example.com']));

    Member::query()->create([
        'gender' => 'm',
        'name' => 'Max',
        'surname' => 'Muster',
        'active' => true,
        'dateOfBirth' => now()->subYears(50)->toDateString(),
    ]);

    $this->artisan('birthdays:send-reminders')->assertSuccessful();

    $messages = sentMessages();

    expect($messages)->toHaveCount(1)
        ->and(addressesOf($messages[0]->getTo()))->toBe(['office@sg-angerberg.at'])
        ->and(addressesOf($messages[0]->getBcc()))->toBe(['obmann@example.com', 'kassier@example.com']);
});

it('addresses the monthly birthday list to the club and the board in bcc', function () {
    Mail::bcc(['obmann@example.com', 'kassier@example.com'])->send(new BirthdayListMail(
        monthName: 'Oktober 2026',
        memberCount: 0,
        pdfContent: '%PDF-1.4',
        pdfFileName: 'Geburtstagsliste_10_2026.pdf',
    ));

    $messages = sentMessages();

    expect(addressesOf($messages[0]->getTo()))->toBe(['office@sg-angerberg.at'])
        ->and(addressesOf($messages[0]->getBcc()))->toBe(['obmann@example.com', 'kassier@example.com']);
});

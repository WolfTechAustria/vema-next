<?php

use App\Models\Setting;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Mail;

test('mails use the configured sender without a club sender', function () {
    config(['mail.from.address' => 'server@example.com', 'mail.from.name' => 'Server']);

    Mail::raw('Hallo', fn ($message) => $message->to('mitglied@example.com'));

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->first();

    expect($sent->getOriginalMessage()->getFrom()[0]->getAddress())->toBe('server@example.com');
});

test('mails use the club sender from the settings', function () {
    Setting::current()->update([
        'name' => 'Trachtenverein Musterdorf',
        'mail_from_address' => 'obmann@musterdorf.at',
    ]);

    Mail::raw('Hallo', fn ($message) => $message->to('mitglied@example.com'));

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->first();
    $from = $sent->getOriginalMessage()->getFrom()[0];

    expect($from->getAddress())->toBe('obmann@musterdorf.at')
        ->and($from->getName())->toBe('Trachtenverein Musterdorf');
});

test('the IMAP account falls back to the server configuration', function () {
    config([
        'imap.accounts.default.host' => 'imap.server.at',
        'imap.accounts.default.username' => 'server@example.com',
        'imap.accounts.default.sent_folder' => 'INBOX.Gesendet',
    ]);

    $account = app(ImapSentMailService::class)->accountConfig();

    expect($account['host'])->toBe('imap.server.at')
        ->and($account['username'])->toBe('server@example.com')
        ->and($account['sent_folder'])->toBe('INBOX.Gesendet');
});

test('the IMAP account from the settings wins over the server configuration', function () {
    config(['imap.accounts.default.host' => 'imap.server.at']);

    Setting::current()->update([
        'imap_host' => 'mail.musterdorf.at',
        'imap_username' => 'obmann@musterdorf.at',
        'imap_password' => 'geheim',
    ]);

    $account = app(ImapSentMailService::class)->accountConfig();

    expect($account)->toMatchArray([
        'host' => 'mail.musterdorf.at',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'obmann@musterdorf.at',
        'password' => 'geheim',
        'sent_folder' => 'INBOX.Sent',
    ]);
});

test('the birthday recipient group falls back to the configuration', function () {
    config(['birthday.recipient_group' => 'Vorstand']);

    expect(Setting::current()->birthdayRecipientGroupName())->toBe('Vorstand');

    Setting::current()->update(['birthday_recipient_group' => 'Ausschuss']);

    expect(Setting::current()->birthdayRecipientGroupName())->toBe('Ausschuss');
});

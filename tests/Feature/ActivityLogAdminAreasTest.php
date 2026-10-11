<?php

use App\Livewire\Admin\Settings\Documents;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\CashBook\Index as CashBookIndex;
use App\Livewire\CashBook\Years as CashBookYears;
use App\Livewire\ExternalContacts\Index as ExternalContactsIndex;
use App\Livewire\Invoices\Articles\Index as ArticlesIndex;
use App\Livewire\Invoices\Create as InvoicesCreate;
use App\Livewire\Invoices\Recipients\Index as RecipientsIndex;
use App\Livewire\RecipientGroups\Index as RecipientGroupsIndex;
use App\Livewire\Skills\Index as SkillsIndex;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use App\Models\ExternalContact;
use App\Models\Invoice;
use App\Models\InvoiceRecipient;
use App\Models\Member;
use App\Models\RecipientGroup;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\User;
use App\Services\ImapSentMailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('document settings', function () {
    it('records letter changes and skips unchanged saves', function () {
        Setting::current()->update(['letter_place' => 'Angerberg']);

        Livewire::test(Documents::class)
            ->call('saveLetter')
            ->set('letter_place', 'Musterdorf')
            ->call('saveLetter')
            ->assertHasNoErrors();

        expect(loggedChanges('settings.letter_updated'))->toBe([
            'Ort in der Datumszeile' => ['field' => 'Ort in der Datumszeile', 'old' => 'Angerberg', 'new' => 'Musterdorf', 'masked' => false],
        ]);
    });

    it('masks the imap password in mail settings', function () {
        Livewire::test(Documents::class)
            ->set('imap_host', 'imap.example.com')
            ->set('imap_username', 'verein@example.com')
            ->set('imap_password', 'geheim123')
            ->call('saveMail')
            ->assertHasNoErrors();

        $changes = loggedChanges('settings.mail_updated');

        expect($changes['IMAP-Server']['new'])->toBe('imap.example.com')
            ->and($changes['IMAP-Passwort'])->toBe(['field' => 'IMAP-Passwort', 'old' => null, 'new' => null, 'masked' => true])
            ->and(json_encode(ActivityLog::query()->pluck('changes')))->not->toContain('geheim123');
    });
});

describe('users', function () {
    beforeEach(function () {
        $this->mock(ImapSentMailService::class)->shouldReceive('append');
    });

    it('records the created user with roles', function () {
        $member = Member::create(['gender' => 'f', 'name' => 'Maria', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(UsersIndex::class)
            ->call('selectMember', $member->memberID)
            ->set('newUsername', 'mariamuster')
            ->set('newEmail', 'maria@example.com')
            ->set('grantAdmin', true)
            ->call('createUser')
            ->assertHasNoErrors();

        $changes = loggedChanges('user.created');

        expect($changes['Benutzername']['new'])->toBe('mariamuster')
            ->and($changes['E-Mail']['new'])->toBe('maria@example.com')
            ->and($changes['Mitglied']['new'])->toBe('Maria Muster')
            ->and($changes['Rollen']['new'])->toBe('Admin');
    });

    it('logs disabling a user and resending the invitation', function () {
        $user = User::factory()->create(['enabled' => true]);

        Livewire::test(UsersIndex::class)
            ->call('toggleEnabled', $user->id)
            ->call('resendInvite', $user->id);

        expect(ActivityLog::query()->where('action', 'user.disabled')->where('subject_id', $user->id)->exists())->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'user.invitation_resent')->sole()->description)->toContain($user->email);
    });
});

describe('cash book', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->year = CashBookYear::factory()->create([
            'name' => '2026/27',
            'start_date' => today()->subMonth()->toDateString(),
            'end_date' => today()->subMonth()->addYear()->subDay()->toDateString(),
            'opening_balance' => 200,
        ]);
    });

    it('records created entries with their values', function () {
        Livewire::test(CashBookIndex::class)
            ->call('openCreate', 'expense')
            ->set('amount', '12,50')
            ->set('description', 'Getränkeeinkauf')
            ->set('newFiles', [UploadedFile::fake()->image('bon.jpg')])
            ->call('saveEntry')
            ->assertHasNoErrors();

        $changes = loggedChanges('cash-book.create');

        expect($changes['Art']['new'])->toBe('Ausgabe')
            ->and($changes['Betrag']['new'])->toBe('12,50')
            ->and($changes['Beschreibung']['new'])->toBe('Getränkeeinkauf')
            ->and($changes['Belege']['new'])->toBe('bon.jpg');
    });

    it('records changed entry fields and removed receipts', function () {
        $entry = CashBookEntry::factory()->for($this->year, 'year')->create([
            'type' => 'expense',
            'date' => today(),
            'amount' => 10,
            'description' => 'Getränke',
            'category' => null,
            'note' => null,
        ]);
        $attachment = $entry->attachments()->create(['file_name' => 'rechnung.pdf', 'file_path' => 'x/rechnung.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100]);

        Livewire::test(CashBookIndex::class)
            ->call('openEdit', $entry->cashBookEntryID)
            ->set('amount', '15')
            ->set('category', 'Fest')
            ->call('saveEntry')
            ->assertHasNoErrors()
            ->call('deleteAttachment', $attachment->attachmentID);

        expect(loggedChanges('cash-book.update'))->toBe([
            'Betrag' => ['field' => 'Betrag', 'old' => '10,00', 'new' => '15,00', 'masked' => false],
            'Kategorie' => ['field' => 'Kategorie', 'old' => null, 'new' => 'Fest', 'masked' => false],
        ])->and(loggedChanges('cash-book.attachment_removed'))->toBe([
            'Belege' => ['field' => 'Belege', 'old' => 'rechnung.pdf', 'new' => null, 'masked' => false],
        ]);
    });

    it('records changed year fields', function () {
        Livewire::test(CashBookYears::class)
            ->call('openEdit', $this->year->cashBookYearID)
            ->set('openingBalance', '250,00')
            ->call('saveYear')
            ->assertHasNoErrors();

        expect(loggedChanges('cash-book.year-update'))->toBe([
            'Anfangsbestand' => ['field' => 'Anfangsbestand', 'old' => '200,00', 'new' => '250,00', 'masked' => false],
        ]);
    });
});

describe('invoices', function () {
    it('records draft changes with compact positions and masked texts', function () {
        $recipient = InvoiceRecipient::create(['company_name' => 'Gasthof Post', 'active' => true]);

        Livewire::test(InvoicesCreate::class)
            ->set('recipientID', $recipient->recipientID)
            ->set('purpose', 'Sommerfest')
            ->set('items.0.description', 'Standmiete')
            ->set('items.0.quantity', '2')
            ->set('items.0.price_net', '50')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $created = loggedChanges('invoice.draft_created');

        expect($created['Empfänger']['new'])->toBe('Gasthof Post')
            ->and($created['Positionen']['new'])->toBe('2 × Standmiete à 50,00');

        Livewire::test(InvoicesCreate::class, ['invoice' => Invoice::sole()])
            ->set('purpose', 'Herbstfest')
            ->set('items.0.quantity', '3')
            ->set('footer_text', '<p>Danke für Ihren Einkauf.</p>')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $updated = loggedChanges('invoice.draft_updated');

        expect($updated['Anlass'])->toBe(['field' => 'Anlass', 'old' => 'Sommerfest', 'new' => 'Herbstfest', 'masked' => false])
            ->and($updated['Positionen']['new'])->toBe('3 × Standmiete à 50,00')
            ->and($updated['Fußtext']['masked'])->toBeTrue()
            ->and($updated)->not->toHaveKey('Empfänger');
    });

    it('records recipient changes and deactivation', function () {
        $recipient = InvoiceRecipient::create(['company_name' => 'Gasthof Post', 'city' => 'Angerberg', 'active' => true]);

        Livewire::test(RecipientsIndex::class)
            ->call('edit', $recipient->recipientID)
            ->set('city', 'Wörgl')
            ->call('save')
            ->assertHasNoErrors()
            ->call('toggleActive', $recipient->recipientID);

        expect(loggedChanges('invoice.recipient_updated'))->toBe([
            'Ort' => ['field' => 'Ort', 'old' => 'Angerberg', 'new' => 'Wörgl', 'masked' => false],
        ])->and(ActivityLog::query()->where('action', 'invoice.recipient_deactivated')->exists())->toBeTrue();
    });

    it('records created and changed articles', function () {
        Livewire::test(ArticlesIndex::class)
            ->call('create')
            ->set('name', 'Standmiete')
            ->set('price_net', '50')
            ->call('save')
            ->assertHasNoErrors()
            ->call('edit', Article::sole()->articleID)
            ->set('price_net', '55,5')
            ->call('save')
            ->assertHasNoErrors();

        expect(loggedChanges('invoice.article_created')['Bezeichnung']['new'])->toBe('Standmiete')
            ->and(loggedChanges('invoice.article_updated'))->toBe([
                'Preis netto' => ['field' => 'Preis netto', 'old' => '50,00', 'new' => '55,50', 'masked' => false],
            ]);
    });
});

describe('groups, contacts and skills', function () {
    it('records group member and contact changes', function () {
        $max = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $eva = Member::create(['gender' => 'f', 'name' => 'Eva', 'surname' => 'Beispiel', 'active' => 1]);
        $contact = ExternalContact::create(['name' => 'Anna', 'surname' => 'Extern', 'email' => 'anna@example.com', 'active' => true]);

        Livewire::test(RecipientGroupsIndex::class)
            ->set('name', 'Vorstand')
            ->set('selectedMembers', [(string) $max->memberID])
            ->call('createGroup')
            ->assertHasNoErrors();

        expect(loggedChanges('group.created')['Mitglieder']['new'])->toBe('Max Muster');

        $group = RecipientGroup::sole();

        Livewire::test(RecipientGroupsIndex::class)
            ->call('editGroup', $group->groupID)
            ->set('selectedMembers', [(string) $max->memberID, (string) $eva->memberID])
            ->set('selectedExternalContacts', [(string) $contact->externalContactID])
            ->call('updateGroup')
            ->assertHasNoErrors()
            ->call('deleteGroup', $group->groupID);

        expect(loggedChanges('group.updated'))->toBe([
            'Mitglieder' => ['field' => 'Mitglieder', 'old' => 'Max Muster', 'new' => 'Eva Beispiel, Max Muster', 'masked' => false],
            'Externe Kontakte' => ['field' => 'Externe Kontakte', 'old' => null, 'new' => 'Extern Anna', 'masked' => false],
        ])->and(ActivityLog::query()->where('action', 'group.deleted')->sole()->description)->toContain('Vorstand');
    });

    it('records external contact changes, deactivation and deletion', function () {
        $contact = ExternalContact::create(['name' => 'Anna', 'surname' => 'Extern', 'email' => 'anna@example.com', 'active' => true]);

        Livewire::test(ExternalContactsIndex::class)
            ->call('edit', $contact->externalContactID)
            ->set('phone', '0664 123')
            ->call('save')
            ->assertHasNoErrors()
            ->call('toggleActive', $contact->externalContactID)
            ->call('delete', $contact->externalContactID);

        expect(loggedChanges('contact.updated'))->toBe([
            'Telefon' => ['field' => 'Telefon', 'old' => null, 'new' => '0664 123', 'masked' => false],
        ])->and(ActivityLog::query()->where('action', 'contact.deactivated')->exists())->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'contact.deleted')->sole()->description)->toContain('anna@example.com');
    });

    it('records the contact import with counts', function () {
        $csv = "Vorname;Nachname;E-Mail\nAnna;Extern;anna@example.com\n";

        Livewire::test(ExternalContactsIndex::class)
            ->set('showImport', true)
            ->set('importFile', UploadedFile::fake()->createWithContent('kontakte.csv', $csv))
            ->call('runImport')
            ->assertHasNoErrors();

        expect(ActivityLog::query()->where('action', 'contact.imported')->sole()->description)
            ->toContain(ExternalContact::count().' angelegt');
    });

    it('records skill creation, changes and deletion', function () {
        Livewire::test(SkillsIndex::class)
            ->set('name', 'Erste Hilfe')
            ->call('createSkill')
            ->assertHasNoErrors();

        $skill = Skill::sole();

        Livewire::test(SkillsIndex::class)
            ->call('editSkill', $skill->skillID)
            ->set('active', false)
            ->call('updateSkill')
            ->assertHasNoErrors()
            ->call('deleteSkill', $skill->skillID);

        expect(loggedChanges('skill.created')['Name']['new'])->toBe('Erste Hilfe')
            ->and(loggedChanges('skill.updated'))->toBe([
                'Aktiv' => ['field' => 'Aktiv', 'old' => 'Ja', 'new' => 'Nein', 'masked' => false],
            ])
            ->and(ActivityLog::query()->where('action', 'skill.deleted')->sole()->description)->toContain('Erste Hilfe');
    });
});

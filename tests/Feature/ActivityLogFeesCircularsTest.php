<?php

use App\Livewire\Circulars\Index as CircularsIndex;
use App\Livewire\MembershipFees\Index as MembershipFeesIndex;
use App\Livewire\Templates\Index as TemplatesIndex;
use App\Models\ActivityLog;
use App\Models\Circular;
use App\Models\CircularRecipient;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeePrescription;
use App\Models\MembershipFeeYear;
use App\Models\Template;
use App\Models\User;
use App\Services\ImapSentMailService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->mock(ImapSentMailService::class)->shouldReceive('append');
    Storage::fake('local');
});

/**
 * @param  list<string>  $surnames
 */
function feeYearWithEntries(array $surnames, array $yearAttributes = []): MembershipFeeYear
{
    $year = MembershipFeeYear::create([
        'year' => 2026,
        'name' => 'Mitgliedsbeitrag 2026',
        'default_amount' => 25,
        'due_date' => '2026-03-31',
        'active' => true,
        ...$yearAttributes,
    ]);

    foreach ($surnames as $surname) {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => $surname, 'active' => 1]);
        MemberEmail::create(['memberID' => $member->memberID, 'email' => strtolower($surname).'@example.com']);

        MembershipFeeEntry::create([
            'yearID' => $year->yearID,
            'memberID' => $member->memberID,
            'amount' => 25,
            'status' => 'open',
        ]);
    }

    return $year;
}

function circularEmailRecipient(Circular $circular, string $email): CircularRecipient
{
    $contact = ExternalContact::create(['name' => 'Kontakt', 'surname' => 'Huber', 'email' => $email]);

    return CircularRecipient::create([
        'circularID' => $circular->circularID,
        'externalContactID' => $contact->externalContactID,
        'delivery_method' => 'email',
        'email' => $email,
    ]);
}

describe('membership fees', function () {
    it('records a created fee year', function () {
        Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(MembershipFeesIndex::class)
            ->set('newYear', 2027)
            ->set('newYearName', 'Mitgliedsbeitrag 2027')
            ->set('newYearAmount', '30,50')
            ->set('newYearDueDate', '2027-03-31')
            ->call('createYear')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'fee.year_created')->sole();

        expect($entry->description)->toBe('Beitragsjahr 2027 „Mitgliedsbeitrag 2027“ wurde mit 1 Beiträgen angelegt.')
            ->and(collect($entry->changes)->pluck('new', 'field')->all())->toBe([
                'Jahr' => '2027',
                'Bezeichnung' => 'Mitgliedsbeitrag 2027',
                'Standardbetrag' => '30,50 €',
                'Fälligkeit' => '31.03.2027',
            ]);
    });

    it('records closing and reopening a fee year', function () {
        $year = feeYearWithEntries([]);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->call('toggleYearActive')
            ->call('toggleYearActive');

        expect(ActivityLog::query()->where('action', 'fee.year_closed')->sole()->description)
            ->toBe('Beitragsjahr 2026 wurde abgeschlossen.')
            ->and(ActivityLog::query()->where('action', 'fee.year_reopened')->count())->toBe(1);
    });

    it('records a changed default amount only when it changed', function () {
        $year = feeYearWithEntries([]);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->set('defaultAmount', '25,00')
            ->call('saveDefaultAmount')
            ->set('defaultAmount', '28')
            ->call('saveDefaultAmount');

        expect(loggedChanges('fee.default_amount_updated'))->toBe([
            'Standardbetrag' => ['field' => 'Standardbetrag', 'old' => '25,00 €', 'new' => '28,00 €', 'masked' => false],
        ]);
    });

    it('summarises applying the default amount in one entry', function () {
        $year = feeYearWithEntries(['Huber', 'Muster', 'Gruber']);
        MembershipFeeEntry::query()->update(['amount' => 20]);
        MembershipFeeEntry::query()->whereHas('member', fn ($query) => $query->where('surname', 'Gruber'))->update(['amount' => 25]);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->call('applyDefaultAmount');

        $entry = ActivityLog::query()->where('action', 'fee.default_amount_applied')->sole();

        expect($entry->description)->toBe('Standardbetrag 25,00 € wurde im Beitragsjahr 2026 auf 2 offene Beiträge angewendet.')
            ->and(collect($entry->changes)->pluck('new', 'field')->all())->toBe([
                'Betrag' => '25,00 €',
                'Mitglieder' => 'Max Huber, Max Muster',
            ])
            ->and(MembershipFeeEntry::query()->pluck('amount')->unique()->all())->toBe(['25.00']);
    });

    it('records a single entry status change with paid date', function () {
        $year = feeYearWithEntries(['Muster']);
        $feeEntry = MembershipFeeEntry::query()->sole();

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->call('updateStatus', $feeEntry->entryID, 'paid');

        $entry = ActivityLog::query()->where('action', 'fee.entry_updated')->sole();

        expect($entry->description)->toBe('Beitrag 2026 von „Max Muster“ wurde als bezahlt markiert.')
            ->and($entry->subject_type)->toBe('MembershipFeeEntry')
            ->and(collect($entry->changes)->keyBy('field')->all())->toBe([
                'Status' => ['field' => 'Status', 'old' => 'Offen', 'new' => 'Bezahlt', 'masked' => false],
                'Bezahlt am' => ['field' => 'Bezahlt am', 'old' => null, 'new' => now()->format('d.m.Y'), 'masked' => false],
            ]);
    });

    it('records a changed amount but not an unchanged one', function () {
        $year = feeYearWithEntries(['Muster']);
        $feeEntry = MembershipFeeEntry::query()->sole();

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->call('updateAmount', $feeEntry->entryID, '25,00')
            ->call('updateAmount', $feeEntry->entryID, '15');

        expect(loggedChanges('fee.entry_updated'))->toBe([
            'Betrag' => ['field' => 'Betrag', 'old' => '25,00 €', 'new' => '15,00 €', 'masked' => false],
        ]);
    });

    it('records changed reminder settings', function () {
        $year = feeYearWithEntries([], ['first_reminder_after_days' => 14, 'reminder_interval_days' => 14, 'max_reminders' => 3]);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->set('firstReminderAfterDays', 14)
            ->set('reminderIntervalDays', 14)
            ->set('maxReminders', 3)
            ->call('saveReminderSettings')
            ->set('maxReminders', 5)
            ->call('saveReminderSettings');

        expect(loggedChanges('fee.reminder_settings_updated'))->toBe([
            'Maximale Erinnerungen' => ['field' => 'Maximale Erinnerungen', 'old' => '3', 'new' => '5', 'masked' => false],
        ]);
    });

    it('summarises sending prescriptions by email in one entry', function () {
        $year = feeYearWithEntries(['Huber', 'Muster', 'Gruber']);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->set('selectedEntries', MembershipFeeEntry::query()->pluck('entryID')->map(fn ($id) => (string) $id)->all())
            ->call('sendSelectedByEmail');

        $entry = ActivityLog::query()->where('action', 'fee.prescriptions_sent')->sole();

        expect($entry->description)->toBe('Beitragsvorschreibungen 2026 wurden an 3 Mitglieder per E-Mail versendet.')
            ->and($entry->subject_id)->toBe($year->yearID)
            ->and(collect($entry->changes)->pluck('new', 'field')->all())->toBe(['Mitglieder' => 'Max Gruber, Max Huber, Max Muster']);
    });

    it('summarises sending reminders in one entry', function () {
        $year = feeYearWithEntries(['Huber', 'Muster'], ['due_date' => now()->subMonths(2)->toDateString(), 'first_reminder_after_days' => 14]);

        foreach (MembershipFeeEntry::query()->get() as $feeEntry) {
            MembershipFeePrescription::create([
                'entryID' => $feeEntry->entryID,
                'memberID' => $feeEntry->memberID,
                'yearID' => $year->yearID,
                'type' => 'prescription',
                'file_path' => 'x.pdf',
                'file_name' => 'x.pdf',
                'sent_at' => now()->subMonths(2),
            ]);
        }

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $year->yearID)
            ->set('selectedEntries', MembershipFeeEntry::query()->pluck('entryID')->map(fn ($id) => (string) $id)->all())
            ->call('sendSelectedReminders');

        expect(ActivityLog::query()->where('action', 'fee.reminders_sent')->sole()->description)
            ->toBe('Erinnerungen 2026 wurden an 2 Mitglieder per E-Mail versendet.');
    });

    it('summarises printed reminders for post in one entry', function () {
        $year = feeYearWithEntries(['Huber', 'Muster'], ['due_date' => now()->subMonths(2)->toDateString(), 'first_reminder_after_days' => 14]);

        foreach (MembershipFeeEntry::query()->get() as $feeEntry) {
            MembershipFeePrescription::create([
                'entryID' => $feeEntry->entryID,
                'memberID' => $feeEntry->memberID,
                'yearID' => $year->yearID,
                'type' => 'prescription',
                'file_path' => 'x.pdf',
                'file_name' => 'x.pdf',
                'sent_at' => now()->subMonths(2),
            ]);
        }

        $this->get(route('membership-fees.reminders.selected', ['entries' => MembershipFeeEntry::query()->pluck('entryID')->implode(',')]))
            ->assertOk();

        $entry = ActivityLog::query()->where('action', 'fee.reminders_printed')->sole();

        expect($entry->description)->toBe('Erinnerungen 2026 für 2 Mitglieder wurden für den Postversand als PDF erstellt.')
            ->and(collect($entry->changes)->pluck('new', 'field')->all())->toBe(['Mitglieder' => 'Max Huber, Max Muster']);
    });
});

describe('circulars', function () {
    it('records a created circular with masked text', function () {
        $first = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $second = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);

        Livewire::test(CircularsIndex::class)
            ->set('title', 'Einladung JHV')
            ->set('subject', 'Jahreshauptversammlung')
            ->set('bodyHtml', '<p>Geheimer langer Text</p>')
            ->set('selectedRecipients', [(string) $first->memberID, (string) $second->memberID])
            ->call('createCircular')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'circular.created')->sole();
        $changes = collect($entry->changes)->keyBy('field');

        expect($entry->description)->toBe('Rundschreiben „Einladung JHV“ wurde als Entwurf angelegt.')
            ->and($changes['Titel']['new'])->toBe('Einladung JHV')
            ->and($changes['Betreff']['new'])->toBe('Jahreshauptversammlung')
            ->and($changes['Text'])->toBe(['field' => 'Text', 'old' => null, 'new' => null, 'masked' => true])
            ->and($changes['Empfänger']['new'])->toBe('2 (Anna Huber, Max Muster)')
            ->and(json_encode($entry->changes))->not->toContain('Geheimer');
    });

    it('records changed circular fields and skips unchanged saves', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $circular = Circular::create(['title' => 'Einladung', 'subject' => 'JHV', 'body_html' => '<p>Alt</p>', 'status' => 'draft']);
        CircularRecipient::create(['circularID' => $circular->circularID, 'memberID' => $member->memberID, 'delivery_method' => 'post']);

        Livewire::test(CircularsIndex::class)
            ->call('editCircular', $circular->circularID)
            ->call('createCircular')
            ->call('editCircular', $circular->circularID)
            ->set('title', 'Einladung JHV 2026')
            ->set('bodyHtml', '<p>Neu</p>')
            ->call('createCircular')
            ->assertHasNoErrors();

        expect(loggedChanges('circular.updated'))->toBe([
            'Titel' => ['field' => 'Titel', 'old' => 'Einladung', 'new' => 'Einladung JHV 2026', 'masked' => false],
            'Text' => ['field' => 'Text', 'old' => null, 'new' => null, 'masked' => true],
        ]);
    });

    it('records sending a circular by email with the recipient count', function () {
        $circular = Circular::create(['title' => 'Einladung JHV', 'subject' => 'Einladung', 'body_html' => '<p>Hallo</p>', 'status' => 'draft']);

        foreach (['a@example.com', 'b@example.com'] as $email) {
            circularEmailRecipient($circular, $email);
        }

        $this->post(route('circulars.send-emails', $circular))->assertRedirect();

        expect(ActivityLog::query()->where('action', 'circular.emails_sent')->sole()->description)
            ->toBe('Rundschreiben „Einladung JHV“ wurde an 2 Empfänger per E-Mail versendet.');
    });

    it('records a test mail', function () {
        $circular = Circular::create(['title' => 'Einladung JHV', 'subject' => 'Einladung', 'body_html' => '<p>Hallo</p>', 'status' => 'draft']);
        $recipient = circularEmailRecipient($circular, 'a@example.com');

        $this->post(route('circulars.test-mail', [$circular, $recipient]))->assertRedirect();

        expect(ActivityLog::query()->where('action', 'circular.test_mail_sent')->sole()->description)
            ->toBe('Testmail zum Rundschreiben „Einladung JHV“ wurde an „Huber Kontakt“ (a@example.com) versendet.')
            ->and(ActivityLog::query()->where('action', 'circular.emails_sent')->exists())->toBeFalse();
    });

    it('records marking the post delivery as done', function () {
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $circular = Circular::create(['title' => 'Einladung JHV', 'body_html' => '', 'status' => 'draft']);
        CircularRecipient::create(['circularID' => $circular->circularID, 'memberID' => $member->memberID, 'delivery_method' => 'post']);

        $this->post(route('circulars.mark-post-sent', $circular))->assertRedirect();

        expect(ActivityLog::query()->where('action', 'circular.post_marked_sent')->sole()->description)
            ->toBe('Postversand des Rundschreibens „Einladung JHV“ an 1 Empfänger wurde als erledigt markiert.');
    });
});

describe('templates', function () {
    it('records template changes with masked text and skips unchanged saves', function () {
        $template = Template::query()->where('key', 'membership_fee_prescription')->sole();

        Livewire::test(TemplatesIndex::class)
            ->set('selectedTemplateID', $template->templateID)
            ->call('save')
            ->set('subject', 'Dein Mitgliedsbeitrag {{year}}')
            ->set('bodyHtml', '<p>Neuer Text</p>')
            ->call('save')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->where('action', 'template.updated')->sole();

        expect($entry->description)->toBe('Template „Mitgliedsbeitragsvorschreibung“ wurde geändert.')
            ->and(collect($entry->changes)->keyBy('field')->all())->toBe([
                'Betreff' => ['field' => 'Betreff', 'old' => 'Mitgliedsbeitrag {{year}}', 'new' => 'Dein Mitgliedsbeitrag {{year}}', 'masked' => false],
                'Text' => ['field' => 'Text', 'old' => null, 'new' => null, 'masked' => true],
            ]);
    });
});

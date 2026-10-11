<?php

use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\RecipientGroup;
use App\Services\BirthdayRecipientService;
use Illuminate\Support\Facades\Schema;

/*
 * Frische Datenbanken (neu registrierte Vereine) bekamen die Zuordnung
 * Empfängergruppe ↔ externer Kontakt nie, weil deren Migration leer war.
 */

it('creates the group/contact table on a fresh database', function () {
    expect(Schema::hasTable('tb_recipient_group_external_contacts'))->toBeTrue()
        ->and(Schema::hasColumns('tb_recipient_group_external_contacts', ['id', 'groupID', 'externalContactID', 'created_at', 'updated_at']))->toBeTrue();
});

it('sends birthday mails to members and external contacts of the board group', function () {
    $group = RecipientGroup::create(['name' => 'Vorstand']);

    $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
    MemberEmail::create(['memberID' => $member->memberID, 'email' => 'obmann@example.com']);
    $group->members()->attach($member->memberID);

    $contact = ExternalContact::create(['name' => 'Anna', 'surname' => 'Extern', 'email' => 'ehrenobfrau@example.com', 'active' => true]);
    $group->externalContacts()->attach($contact->externalContactID);

    expect(app(BirthdayRecipientService::class)->resolveEmails()->sort()->values()->all())
        ->toBe(['ehrenobfrau@example.com', 'obmann@example.com']);
});

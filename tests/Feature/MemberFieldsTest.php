<?php

use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use App\Livewire\Admin\Settings\MemberFields as MemberFieldSettings;
use App\Livewire\MemberPortal\Profile;
use App\Livewire\Members\Create as MembersCreate;
use App\Livewire\Members\Edit as MembersEdit;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberField;
use App\Models\User;
use App\Services\MemberFields;
use App\Services\TemplateRendererService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function memberField(string $name, MemberFieldType $type, array $attributes = []): MemberField
{
    return MemberField::create([
        'name' => $name,
        'key' => str($name)->slug('_')->toString(),
        'type' => $type,
        'portal' => MemberFieldPortal::Hidden,
        'sort_order' => MemberField::count() + 1,
        'active' => true,
        ...$attributes,
    ]);
}

function fieldTestMember(): Member
{
    return Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
}

describe('settings', function () {
    it('creates a select field with a placeholder key', function () {
        Livewire::actingAs($this->admin)
            ->test(MemberFieldSettings::class)
            ->call('newField')
            ->set('name', 'Größe Tracht')
            ->set('type', 'select')
            ->set('options', "S\nM\n\nL\nM")
            ->set('portal', 'editable')
            ->call('saveField')
            ->assertHasNoErrors()
            ->assertSee('{{feld_groesse_tracht}}');

        $field = MemberField::query()->sole();

        expect($field->options)->toBe(['S', 'M', 'L'])
            ->and($field->portal)->toBe(MemberFieldPortal::Editable)
            ->and(ActivityLog::query()->where('action', 'member_field.created')->exists())->toBeTrue();
    });

    it('requires options for a select field', function () {
        Livewire::actingAs($this->admin)
            ->test(MemberFieldSettings::class)
            ->call('newField')
            ->set('name', 'Register')
            ->set('type', 'select')
            ->call('saveField')
            ->assertHasErrors('options');
    });

    it('locks the type and used options once values exist', function () {
        $field = memberField('Register', MemberFieldType::Select, ['options' => ['Flöte', 'Trompete']]);
        app(MemberFields::class)->save(fieldTestMember(), [$field->fieldID => 'Trompete']);

        Livewire::actingAs($this->admin)
            ->test(MemberFieldSettings::class)
            ->call('editField', $field->fieldID)
            ->set('type', 'text')
            ->call('saveField')
            ->assertHasErrors('type')
            ->set('type', 'select')
            ->set('options', "Flöte\nKlarinette")
            ->call('saveField')
            ->assertHasErrors('options');

        expect($field->fresh()->options)->toBe(['Flöte', 'Trompete']);
    });

    it('deletes a field with its values and logs how many were lost', function () {
        $field = memberField('Instrument', MemberFieldType::Text);
        app(MemberFields::class)->save(fieldTestMember(), [$field->fieldID => 'Tuba']);

        Livewire::actingAs($this->admin)
            ->test(MemberFieldSettings::class)
            ->call('deleteField', $field->fieldID);

        expect(MemberField::count())->toBe(0)
            ->and(ActivityLog::query()->where('action', 'member_field.deleted')->sole()->description)->toContain('mit 1 Wert');
    });

    it('is only open to admins', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.member-fields'))
            ->assertForbidden();
    });
});

describe('member data', function () {
    beforeEach(function () {
        $this->text = memberField('Instrument', MemberFieldType::Text);
        $this->number = memberField('Schuhgröße', MemberFieldType::Number);
        $this->date = memberField('Tracht seit', MemberFieldType::Date);
        $this->boolean = memberField('Trachtenträger', MemberFieldType::Boolean);
        $this->select = memberField('Register', MemberFieldType::Select, ['options' => ['Flöte', 'Trompete']]);
    });

    it('saves all field types when creating a member and shows them', function () {
        Livewire::actingAs($this->admin)
            ->test(MembersCreate::class)
            ->assertSee('Weitere Angaben')
            ->set('gender', 'w')
            ->set('name', 'Anna')
            ->set('surname', 'Huber')
            ->set("customFields.{$this->text->fieldID}", 'Tuba')
            ->set("customFields.{$this->number->fieldID}", '42,5')
            ->set("customFields.{$this->date->fieldID}", '2020-05-01')
            ->set("customFields.{$this->boolean->fieldID}", true)
            ->set("customFields.{$this->select->fieldID}", 'Trompete')
            ->call('save')
            ->assertHasNoErrors();

        $member = Member::query()->sole();

        expect(app(MemberFields::class)->displayValues($member))->toBe([
            'Instrument' => 'Tuba',
            'Schuhgröße' => '42,5',
            'Tracht seit' => '01.05.2020',
            'Trachtenträger' => 'Ja',
            'Register' => 'Trompete',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MembersShow::class, ['member' => $member])
            ->assertSee('Weitere Angaben')
            ->assertSee('01.05.2020')
            ->assertSee('Trompete');
    });

    it('rejects invalid values', function () {
        $member = fieldTestMember();

        Livewire::actingAs($this->admin)
            ->test(MembersEdit::class, ['member' => $member])
            ->set("customFields.{$this->number->fieldID}", 'viel')
            ->set("customFields.{$this->date->fieldID}", '31.12.2020')
            ->set("customFields.{$this->select->fieldID}", 'Geige')
            ->call('save')
            ->assertHasErrors([
                "customFields.{$this->number->fieldID}",
                "customFields.{$this->date->fieldID}",
                "customFields.{$this->select->fieldID}",
            ]);
    });

    it('logs each changed field with old and new value', function () {
        $member = fieldTestMember();
        app(MemberFields::class)->save($member, [$this->select->fieldID => 'Flöte', $this->text->fieldID => 'Tuba']);

        Livewire::actingAs($this->admin)
            ->test(MembersEdit::class, ['member' => $member])
            ->assertSet("customFields.{$this->select->fieldID}", 'Flöte')
            ->set("customFields.{$this->select->fieldID}", 'Trompete')
            ->set("customFields.{$this->boolean->fieldID}", true)
            ->set("customFields.{$this->text->fieldID}", '')
            ->call('save')
            ->assertHasNoErrors();

        $changes = collect(ActivityLog::query()->where('action', 'member.updated')->sole()->changes)->keyBy('field');

        expect($changes['Register'])->toMatchArray(['old' => 'Flöte', 'new' => 'Trompete'])
            ->and($changes['Trachtenträger'])->toMatchArray(['old' => 'Nein', 'new' => 'Ja'])
            ->and($changes['Instrument'])->toMatchArray(['old' => 'Tuba', 'new' => null])
            ->and($changes)->not->toHaveKey('Schuhgröße');
    });

    it('filters the member list by a field', function () {
        $trumpet = fieldTestMember();
        $flute = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Flötist', 'active' => 1]);
        app(MemberFields::class)->save($trumpet, [$this->select->fieldID => 'Trompete', $this->boolean->fieldID => true]);
        app(MemberFields::class)->save($flute, [$this->select->fieldID => 'Flöte']);

        Livewire::actingAs($this->admin)
            ->test(MembersIndex::class)
            ->set('fieldFilter', (string) $this->select->fieldID)
            ->set('fieldFilterValue', 'Trompete')
            ->assertSee('Huber')
            ->assertDontSee('Flötist')
            ->set('fieldFilter', (string) $this->boolean->fieldID)
            ->set('fieldFilterValue', '0')
            ->assertSee('Flötist')
            ->assertDontSee('Huber');
    });

    it('fills placeholders in circulars', function () {
        $member = fieldTestMember();
        app(MemberFields::class)->save($member, [$this->select->fieldID => 'Trompete']);

        $html = app(TemplateRendererService::class)->circular('<p>Register: {{feld_register}}, Instrument: {{feld_instrument}}.</p>', $member);

        expect($html)->toBe('<p>Register: Trompete, Instrument: .</p>');
    });
});

describe('member portal', function () {
    it('shows visible fields and saves only editable ones', function () {
        $hidden = memberField('Interne Notiz', MemberFieldType::Text);
        $visible = memberField('Verbandsnummer', MemberFieldType::Text, ['portal' => MemberFieldPortal::Visible]);
        $editable = memberField('Instrument', MemberFieldType::Text, ['portal' => MemberFieldPortal::Editable]);

        $member = loginPortalMember();
        $member->update(['street' => 'Dorfstraße 1', 'zip' => '6300']);
        app(MemberFields::class)->save($member, [
            $hidden->fieldID => 'geheim',
            $visible->fieldID => 'V-123',
        ]);

        Livewire::test(Profile::class)
            ->assertSee('V-123')
            ->assertDontSee('geheim')
            ->set("customFields.{$editable->fieldID}", 'Klarinette')
            ->set("customFields.{$visible->fieldID}", 'V-999')
            ->call('saveProfile')
            ->assertHasNoErrors();

        expect(app(MemberFields::class)->storedValues($member))->toMatchArray([
            $hidden->fieldID => 'geheim',
            $visible->fieldID => 'V-123',
            $editable->fieldID => 'Klarinette',
        ]);

        $changes = collect(ActivityLog::query()->where('action', 'portal.profile_updated')->sole()->changes)->keyBy('field');

        expect($changes['Instrument'])->toMatchArray(['old' => null, 'new' => 'Klarinette']);
    });
});

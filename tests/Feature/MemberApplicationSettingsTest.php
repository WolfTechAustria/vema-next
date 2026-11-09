<?php

use App\Livewire\Admin\Settings\Application as ApplicationSettings;
use App\Livewire\Admin\Settings\MemberFields as MemberFieldSettings;
use App\Livewire\Application\Form as ApplicationForm;
use App\Models\ActivityLog;
use App\Models\MemberField;
use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('is only available to admins', function () {
    $this->get(route('admin.settings.application'))->assertOk()->assertSee('Online-Anmeldung aktivieren');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.application'))
        ->assertForbidden();
});

it('saves texts, fields, membership types, consents and recipients', function () {
    Livewire::test(ApplicationSettings::class)
        ->set('enabled', true)
        ->set('intro', 'Beitrag: € 40,–')
        ->set('privacyText', 'Wir speichern deine Daten.')
        ->set('signature', false)
        ->set('notifyEmails', "obmann@example.com\nkassier@example.com")
        ->set('fields.phone.visible', false)
        ->set('fields.dateOfBirth.required', false)
        ->call('addMembershipType')
        ->set('membershipTypes.2.label', 'Jugendmitglied')
        ->set('membershipTypes.2.kind', 'none')
        ->call('removeConsent', 0)
        ->call('addConsent')
        ->set('consents.0.text', 'Fotos dürfen veröffentlicht werden.')
        ->call('save')
        ->assertHasNoErrors();

    $setting = Setting::current();

    expect($setting->application_enabled)->toBeTrue()
        ->and($setting->application_intro)->toBe('Beitrag: € 40,–')
        ->and($setting->application_signature)->toBeFalse()
        ->and($setting->application_notify_emails)->toBe(['obmann@example.com', 'kassier@example.com'])
        ->and($setting->application_fields['phone'])->toBe(['visible' => false, 'required' => false])
        ->and($setting->application_fields['dateOfBirth'])->toBe(['visible' => true, 'required' => false])
        ->and(collect($setting->application_membership_types)->pluck('label')->all())->toBe(['Ordentliches Mitglied', 'Unterstützendes Mitglied', 'Jugendmitglied'])
        ->and($setting->application_consents)->toBe([['text' => 'Fotos dürfen veröffentlicht werden.', 'required' => false]]);

    expect(ActivityLog::where('action', 'settings.application_updated')->exists())->toBeTrue();
});

it('rejects invalid notification addresses', function () {
    Livewire::test(ApplicationSettings::class)
        ->set('notifyEmails', 'keine-adresse')
        ->call('save')
        ->assertHasErrors('notifyEmails');
});

it('asks custom member fields marked for the online application', function () {
    Setting::current()->update(['application_enabled' => true]);

    Livewire::test(MemberFieldSettings::class)
        ->call('newField')
        ->set('name', 'Instrument')
        ->set('onApplication', true)
        ->call('saveField')
        ->assertHasNoErrors();

    expect(MemberField::sole()->on_application)->toBeTrue();

    Livewire::test(ApplicationForm::class)->assertSee('Instrument');
});

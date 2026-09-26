<?php

use App\Livewire\Admin\Settings\Documents;
use App\Models\RecipientGroup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('branding');

    $this->admin = User::factory()->admin()->create();
});

test('only admins can open the document settings', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.documents'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.documents'))
        ->assertOk()
        ->assertSee('Briefpapier & E-Mail');
});

test('letter settings are saved', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('letter_place', 'Musterdorf')
        ->set('signatory_1_title', 'Die Obfrau')
        ->set('signatory_1_name', 'MUSTER Maria')
        ->call('saveLetter')
        ->assertHasNoErrors();

    $setting = Setting::current();

    expect($setting->letter_place)->toBe('Musterdorf')
        ->and($setting->signatory_1_title)->toBe('Die Obfrau')
        ->and($setting->signatory_1_name)->toBe('MUSTER Maria')
        ->and($setting->signatory_2_title)->toBeNull();
});

test('uploads are stored right after selecting a file', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
        ->assertHasNoErrors()
        ->set('letterheadUpload', UploadedFile::fake()->create('briefpapier.pdf', 100, 'application/pdf'))
        ->assertHasNoErrors();

    Storage::disk('branding')->assertExists(['logo.png', 'letterhead.pdf']);
});

test('the letterhead must be a PDF', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('letterheadUpload', UploadedFile::fake()->image('briefpapier.png'))
        ->assertHasErrors('letterheadUpload');

    expect(Setting::current()->letterhead_path)->toBeNull();
});

test('mail settings are saved and the IMAP password is stored encrypted', function () {
    RecipientGroup::create(['name' => 'Ausschuss']);

    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('mail_from_address', 'obmann@musterdorf.at')
        ->set('birthday_recipient_group', 'Ausschuss')
        ->set('imap_host', 'mail.musterdorf.at')
        ->set('imap_username', 'obmann@musterdorf.at')
        ->set('imap_password', 'geheim')
        ->call('saveMail')
        ->assertHasNoErrors()
        ->assertSet('imap_password', '');

    $setting = Setting::current();

    expect($setting->mail_from_address)->toBe('obmann@musterdorf.at')
        ->and($setting->birthdayRecipientGroupName())->toBe('Ausschuss')
        ->and($setting->imap_password)->toBe('geheim');

    $rawPassword = DB::table('tb_settings')->value('imap_password');

    expect($rawPassword)->not->toBe('geheim')
        ->and(Crypt::decryptString($rawPassword))->toBe('geheim');
});

test('an empty password field keeps the stored IMAP password', function () {
    Setting::current()->update([
        'imap_host' => 'mail.musterdorf.at',
        'imap_username' => 'obmann@musterdorf.at',
        'imap_password' => 'geheim',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('imap_sent_folder', 'Sent')
        ->call('saveMail')
        ->assertHasNoErrors();

    expect(Setting::current()->imap_password)->toBe('geheim')
        ->and(Setting::current()->imap_sent_folder)->toBe('Sent');
});

test('the birthday recipient group must exist', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('birthday_recipient_group', 'Gibt es nicht')
        ->call('saveMail')
        ->assertHasErrors('birthday_recipient_group');
});

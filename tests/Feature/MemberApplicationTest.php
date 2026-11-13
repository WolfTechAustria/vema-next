<?php

use App\Enums\MemberApplicationStatus;
use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use App\Enums\MembershipKind;
use App\Livewire\Application\Form as ApplicationForm;
use App\Livewire\Application\Verify as ApplicationVerify;
use App\Livewire\Members\ApplicationShow;
use App\Mail\MemberApplicationConfirmationMail;
use App\Mail\MemberApplicationReceivedMail;
use App\Mail\MemberApplicationRejectedMail;
use App\Mail\MemberApplicationVerifyMail;
use App\Mail\MemberWelcomeMail;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Models\MemberField;
use App\Models\MemberFile;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberFields;
use App\Services\TurnstileVerifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');

    Setting::current()->update([
        'name' => 'Schützengilde Musterdorf',
        'application_enabled' => true,
        'application_intro' => 'Der Mitgliedsbeitrag beträgt € 40,– pro Jahr.',
        'application_notify_emails' => ['obmann@example.com'],
        'application_signature' => false,
        'application_membership_types' => [
            ['label' => 'Ordentliches Mitglied', 'info' => 'Nimmt an Bewerben teil.', 'kind' => 'ordinary'],
            ['label' => 'Unterstützendes Mitglied', 'info' => 'Der Beitrag bleibt beim Verein.', 'kind' => 'supporting'],
        ],
        'application_consents' => [
            ['text' => 'Ich stimme der Datenverarbeitung zu.', 'required' => true],
            ['text' => 'Fotos dürfen veröffentlicht werden.', 'required' => false],
        ],
    ]);
});

function signaturePngDataUrl(): string
{
    $image = imagecreatetruecolor(20, 10);
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

function filledApplicationForm(): Testable
{
    return Livewire::test(ApplicationForm::class)
        ->set('gender', 'w')
        ->set('name', 'Anna')
        ->set('surname', 'Neu')
        ->set('dateOfBirth', '1990-05-01')
        ->set('street', 'Dorfstraße 1')
        ->set('zip', '6300')
        ->set('city', 'Wörgl')
        ->set('email', 'anna@example.com')
        ->set('phone', '0664 123456')
        ->set('place', 'Wörgl')
        ->set('membershipType', '1')
        ->set('consents.0', true);
}

it('is not reachable while the online application is disabled', function () {
    Setting::current()->update(['application_enabled' => false]);

    $this->get(route('application.form'))->assertNotFound();
});

it('shows the configured texts and membership types', function () {
    $this->get(route('application.form'))
        ->assertOk()
        ->assertSee('Der Mitgliedsbeitrag beträgt € 40,– pro Jahr.')
        ->assertSee('Unterstützendes Mitglied')
        ->assertSee('Der Beitrag bleibt beim Verein.')
        ->assertSee('Fotos dürfen veröffentlicht werden.');
});

it('validates required fields, consents and the guardian of minors', function () {
    Livewire::test(ApplicationForm::class)
        ->set('dateOfBirth', now()->subYears(10)->toDateString())
        ->call('submit')
        ->assertHasErrors(['name', 'surname', 'email', 'gender', 'street', 'zip', 'city', 'membershipType', 'consents.0', 'guardianName'])
        ->assertHasNoErrors(['phone', 'consents.1']);

    expect(MemberApplication::count())->toBe(0);
});

it('hides fields that are switched off and requires the signature when enabled', function () {
    Setting::current()->update([
        'application_signature' => true,
        'application_fields' => ['address' => ['visible' => false, 'required' => false]],
    ]);

    Livewire::test(ApplicationForm::class)
        ->assertDontSee('Straße und Hausnummer')
        ->call('submit')
        ->assertHasErrors(['signature'])
        ->assertHasNoErrors(['street', 'zip']);
});

it('stores the application unverified and sends the confirmation link', function () {
    filledApplicationForm()
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('result', 'verify');

    $application = MemberApplication::sole();

    expect($application->status)->toBe(MemberApplicationStatus::Unverified)
        ->and($application->applied_on->toDateString())->toBe(now(config('app.club_timezone'))->toDateString())
        ->and($application->membership_type)->toBe('Unterstützendes Mitglied')
        ->and($application->membership_kind)->toBe(MembershipKind::Supporting)
        ->and($application->consents)->toBe([
            ['text' => 'Ich stimme der Datenverarbeitung zu.', 'accepted' => true],
            ['text' => 'Fotos dürfen veröffentlicht werden.', 'accepted' => false],
        ]);

    Mail::assertSent(MemberApplicationVerifyMail::class, fn ($mail) => $mail->hasTo('anna@example.com'));
    Mail::assertNotSent(MemberApplicationReceivedMail::class);
});

it('forwards the application once the e-mail address is confirmed', function () {
    filledApplicationForm()->call('submit');

    $token = null;
    Mail::assertSent(MemberApplicationVerifyMail::class, function (MemberApplicationVerifyMail $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    Livewire::test(ApplicationVerify::class, ['token' => $token])
        ->assertSet('invalid', false)
        ->call('confirm')
        ->assertSet('confirmed', true);

    expect(MemberApplication::sole()->status)->toBe(MemberApplicationStatus::Pending);

    Mail::assertSent(MemberApplicationReceivedMail::class, fn ($mail) => $mail->hasTo('obmann@example.com'));
    Mail::assertSent(MemberApplicationConfirmationMail::class, fn ($mail) => $mail->hasTo('anna@example.com'));

    // Der Link ist danach verbraucht.
    Livewire::test(ApplicationVerify::class, ['token' => $token])->assertSet('invalid', true);
});

it('rejects expired confirmation links', function () {
    filledApplicationForm()->call('submit');

    $token = null;
    Mail::assertSent(MemberApplicationVerifyMail::class, function (MemberApplicationVerifyMail $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    $this->travel(73)->hours();

    Livewire::test(ApplicationVerify::class, ['token' => $token])->assertSet('invalid', true);
    Livewire::test(ApplicationVerify::class, ['token' => 'falsch'])->assertSet('invalid', true);
});

it('silently drops submissions that fill the honeypot', function () {
    filledApplicationForm()
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('result', 'verify');

    expect(MemberApplication::count())->toBe(0);
    Mail::assertNothingSent();
});

it('limits the number of submissions per hour', function () {
    foreach (range(1, 5) as $attempt) {
        RateLimiter::hit('member-application:127.0.0.1', 3600);
    }

    filledApplicationForm()->call('submit')->assertHasErrors('form');

    expect(MemberApplication::count())->toBe(0);
});

describe('captcha', function () {
    beforeEach(function () {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Setting::current()->update(['application_spam_protection' => 'captcha']);
    });

    it('accepts the application directly after a solved captcha', function () {
        Http::fake([TurnstileVerifier::VERIFY_URL => Http::response(['success' => true])]);

        filledApplicationForm()
            ->set('captchaToken', 'token')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('result', 'done');

        expect(MemberApplication::sole()->status)->toBe(MemberApplicationStatus::Pending);
        Mail::assertSent(MemberApplicationReceivedMail::class);
        Mail::assertNotSent(MemberApplicationVerifyMail::class);
    });

    it('refuses the application when the captcha fails', function () {
        Http::fake([TurnstileVerifier::VERIFY_URL => Http::response(['success' => false])]);

        filledApplicationForm()
            ->set('captchaToken', 'token')
            ->call('submit')
            ->assertHasErrors('captcha')
            ->assertDispatched('captcha-reset');

        expect(MemberApplication::count())->toBe(0);
    });

    it('falls back to e-mail confirmation without captcha keys', function () {
        config(['services.turnstile.secret_key' => null]);

        filledApplicationForm()->call('submit')->assertSet('result', 'verify');

        Mail::assertSent(MemberApplicationVerifyMail::class);
    });
});

it('stores the signature of the applicant', function () {
    Setting::current()->update(['application_signature' => true]);

    filledApplicationForm()
        ->set('signature', signaturePngDataUrl())
        ->call('submit')
        ->assertHasNoErrors();

    $application = MemberApplication::sole();

    Storage::disk('local')->assertExists($application->signature_path);

    filledApplicationForm()
        ->set('signature', 'data:image/png;base64,'.base64_encode('kein Bild'))
        ->call('submit')
        ->assertHasErrors('signature');
});

describe('processing', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('lists open applications and counts them on the member list', function () {
        MemberApplication::factory()->create(['name' => 'Offene', 'surname' => 'Anmeldung']);
        MemberApplication::factory()->unverified()->create(['name' => 'Unbestätigte', 'surname' => 'Anmeldung']);

        $this->get(route('members.applications.index'))
            ->assertOk()
            ->assertSee('Offene Anmeldung')
            ->assertDontSee('Unbestätigte Anmeldung');

        $this->get(route('members.index'))->assertSee('1 neue Online-Anmeldung wartet');
    });

    it('creates the member with the join date, contact data and the application pdf', function () {
        $application = MemberApplication::factory()->create([
            'gender' => 'w',
            'name' => 'Anna',
            'surname' => 'Neu',
            'email' => 'anna@example.com',
            'phone' => '0664 123456',
            'membership_kind' => MembershipKind::Supporting,
            'applied_on' => '2026-11-01',
        ]);

        Livewire::test(ApplicationShow::class, ['application' => $application])
            ->call('approve')
            ->assertHasNoErrors()
            ->assertRedirect();

        $member = Member::where('surname', 'Neu')->sole();

        expect(Carbon::parse($member->dateOfJoin)->toDateString())->toBe('2026-11-01')
            ->and((bool) $member->supportingMember)->toBeTrue()
            ->and((bool) $member->competitionMember)->toBeFalse()
            ->and((int) $member->active)->toBe(1)
            ->and($member->emails()->pluck('email')->all())->toBe(['anna@example.com'])
            ->and($member->phones()->pluck('phoneNumber')->all())->toBe(['0664 123456'])
            ->and($member->currentMembershipPeriod?->date_from?->toDateString())->toBe('2026-11-01');

        $application->refresh();
        expect($application->status)->toBe(MemberApplicationStatus::Approved)
            ->and($application->memberID)->toBe($member->memberID);

        $file = MemberFile::where('memberID', $member->memberID)->sole();
        expect($file->category)->toBe('Beitrittserklärung')
            ->and($file->visible_to_member)->toBeFalse();
        Storage::disk('local')->assertExists($file->file_path);

        Mail::assertSent(MemberWelcomeMail::class);
    });

    it('needs a salutation before approval', function () {
        $application = MemberApplication::factory()->create(['gender' => null]);

        Livewire::test(ApplicationShow::class, ['application' => $application])
            ->call('approve')
            ->assertHasErrors('gender')
            ->call('edit')
            ->set('gender', 'm')
            ->call('save')
            ->call('approve')
            ->assertHasNoErrors();

        expect(Member::count())->toBe(1);
    });

    it('rejects an application, removes the signature and informs the applicant', function () {
        Storage::disk('local')->put('applications/1/signature.png', 'png');
        $application = MemberApplication::factory()->create(['signature_path' => 'applications/1/signature.png']);

        Livewire::test(ApplicationShow::class, ['application' => $application])
            ->set('rejectionNote', 'Bitte persönlich beim Training melden.')
            ->set('notifyApplicant', true)
            ->call('reject');

        $application->refresh();

        expect($application->status)->toBe(MemberApplicationStatus::Rejected)
            ->and($application->signature_path)->toBeNull()
            ->and(Member::count())->toBe(0);

        Storage::disk('local')->assertMissing('applications/1/signature.png');
        Mail::assertSent(MemberApplicationRejectedMail::class, fn ($mail) => $mail->hasTo($application->email));
    });

    it('renders the application as pdf', function () {
        $application = MemberApplication::factory()->create();

        $this->get(route('members.applications.pdf', $application))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    });
});

it('prunes unverified applications after a week', function () {
    $old = MemberApplication::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
    $recent = MemberApplication::factory()->unverified()->create();
    $pending = MemberApplication::factory()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('applications:prune')->assertSuccessful();

    expect(MemberApplication::pluck('applicationID')->all())->toEqualCanonicalizing([$recent->applicationID, $pending->applicationID]);
});

it('redirects the homepage address to the form', function () {
    HomepageSetting::current()->update(['enabled' => true]);

    $this->get('/site/anmeldung')->assertRedirect(route('application.form'));

    Setting::current()->update(['application_enabled' => false]);

    $this->get('/site/anmeldung')->assertNotFound();
});

it('takes over multiple choice member fields from the application', function () {
    $field = MemberField::create([
        'name' => 'Disziplinen', 'key' => 'disziplinen', 'type' => MemberFieldType::MultiSelect,
        'options' => ['Luftgewehr', 'Luftpistole'], 'portal' => MemberFieldPortal::Hidden,
        'on_application' => true, 'sort_order' => 1, 'active' => true,
    ]);

    Setting::current()->update(['application_spam_protection' => 'email']);

    filledApplicationForm()
        ->set("customFields.{$field->fieldID}", ['Luftpistole', 'Luftgewehr'])
        ->call('submit')
        ->assertHasNoErrors();

    $application = MemberApplication::sole();
    $application->update(['status' => MemberApplicationStatus::Pending]);

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(ApplicationShow::class, ['application' => $application])->call('approve')->assertHasNoErrors();

    expect(app(MemberFields::class)->displayValues(Member::sole()))->toBe(['Disziplinen' => 'Luftgewehr, Luftpistole']);
});

<?php

use App\Enums\DutySwapType;
use App\Enums\PushCategory;
use App\Livewire\PushSettings;
use App\Models\DutyPlan;
use App\Models\DutyPlanVolunteer;
use App\Models\Member;
use App\Models\MemberAccount;
use App\Models\MemberApplication;
use App\Models\MemberEmail;
use App\Models\User;
use App\Notifications\PushMessage;
use App\Services\DutySwaps;
use App\Services\MemberApplications;
use App\Services\PushNotifier;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    config([
        'webpush.vapid.public_key' => 'BPublicTestKey',
        'webpush.vapid.private_key' => 'privateTestKey',
    ]);
    Notification::fake();
    Mail::fake();
});

function subscribeDevice(User|MemberAccount $account, string $device = 'a'): void
{
    $account->updatePushSubscription('https://push.example.test/'.$device, 'p256dh-key', 'auth-token', 'aes128gcm');
}

/**
 * Mitglied mit Mitglieder-Account und registriertem Gerät.
 */
function pushMember(string $name, string $surname): array
{
    $member = Member::create(['gender' => 'w', 'name' => $name, 'surname' => $surname, 'active' => 1]);
    $email = strtolower($surname).'@example.test';
    MemberEmail::create(['memberID' => $member->memberID, 'email' => $email]);
    $account = MemberAccount::create(['memberID' => $member->memberID, 'email' => $email]);
    subscribeDevice($account, strtolower($surname));

    return [$member, $account];
}

describe('settings page', function () {
    it('renders for members and staff and saves a subscription', function () {
        loginPortalMember('Muster');

        $this->get(route('member.notifications'))
            ->assertOk()
            ->assertSee('Push-Benachrichtigungen auf diesem Gerät')
            ->assertSee('Rundschreiben &amp; Dokumente', false)
            ->assertDontSee('Verwaltung');

        $this->actingAs(User::factory()->admin()->create(), 'web');

        $this->get(route('notifications'))
            ->assertOk()
            ->assertSee('Verwaltung')
            ->assertDontSee('Rundschreiben &amp; Dokumente', false);

        Livewire::test(PushSettings::class)
            ->call('subscribe', ['endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'token']])
            ->call('toggleCategory', PushCategory::Administration->value);

        $user = User::query()->sole();
        expect($user->pushSubscriptions()->value('endpoint'))->toBe('https://push.example.test/abc')
            ->and($user->fresh()->wantsPush(PushCategory::Administration))->toBeFalse();
    });

    it('rejects endpoints that are not https and categories of the other audience', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(PushSettings::class)
            ->call('subscribe', ['endpoint' => 'http://evil.test/x'])
            ->assertStatus(422);

        Livewire::test(PushSettings::class)
            ->call('toggleCategory', PushCategory::Duties->value)
            ->assertNotFound();
    });

    it('removes the device again', function () {
        $user = User::factory()->admin()->create();
        subscribeDevice($user);
        $this->actingAs($user);

        Livewire::test(PushSettings::class)->call('unsubscribe', 'https://push.example.test/a');

        expect($user->pushSubscriptions()->exists())->toBeFalse();
    });
});

describe('sending', function () {
    it('reaches members with a device only for enabled categories', function () {
        [$anna, $annaAccount] = pushMember('Anna', 'Huber');
        [$berta, $bertaAccount] = pushMember('Berta', 'Bauer');
        $bertaAccount->togglePushCategory(PushCategory::Events);

        app(PushNotifier::class)->toMembers([$anna, $berta], PushCategory::Events, 'Neuer Termin', 'Sommerfest', '/member/events');

        Notification::assertSentTo($annaAccount, PushMessage::class, fn (PushMessage $message, array $channels) => $channels === [WebPushChannel::class] && $message->title === 'Neuer Termin');
        Notification::assertNotSentTo($bertaAccount, PushMessage::class);
    });

    it('sends nothing without VAPID keys', function () {
        config(['webpush.vapid.public_key' => null]);
        [$anna, $annaAccount] = pushMember('Anna', 'Huber');

        app(PushNotifier::class)->toMembers($anna, PushCategory::Duties, 'Test', 'Test', '/');

        Notification::assertNothingSent();
    });

    it('builds a web push message with the target url', function () {
        $message = (new PushMessage(PushCategory::Duties, 'Neuer Dienst', 'Bar am Freitag', 'https://verein.test/member/duties'))
            ->toWebPush(new User, new PushMessage(PushCategory::Duties, '', '', ''))
            ->toArray();

        expect($message['title'])->toBe('Neuer Dienst')
            ->and($message['body'])->toBe('Bar am Freitag')
            ->and($message['data'])->toBe(['url' => 'https://verein.test/member/duties']);
    });
});

describe('triggers', function () {
    it('notifies the asked member about a duty swap request', function () {
        $requester = loginPortalMember('Muster');
        [$anna, $annaAccount] = pushMember('Anna', 'Huber');
        $plan = DutyPlan::query()->firstOrCreate(['name' => 'Saison'], ['date_from' => today()->toDateString(), 'date_to' => today()->addYear()->toDateString()]);
        DutyPlanVolunteer::create(['planID' => $plan->planID, 'memberID' => $anna->memberID, 'active' => 1, 'weekday_mask' => 127]);

        app(DutySwaps::class)->request($requester, assignDuty($requester), DutySwapType::Handover, $anna);

        Notification::assertSentTo($annaAccount, PushMessage::class, fn (PushMessage $message) => $message->category === PushCategory::Duties
            && str_contains($message->body, 'Max Muster sucht Ersatz'));
    });

    it('notifies admins about a new online application', function () {
        $admin = User::factory()->admin()->create();
        subscribeDevice($admin);
        $application = MemberApplication::factory()->create();

        (fn () => $this->announce($application))->call(app(MemberApplications::class));

        Notification::assertSentTo($admin, PushMessage::class, fn (PushMessage $message) => $message->title === 'Neue Online-Anmeldung');
    });
});

it('serves an installable web app manifest', function () {
    $this->get(route('manifest', ['bereich' => 'mitglied']))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('start_url', '/member/profile')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(2, 'icons');
});

<?php

use App\Livewire\MemberPortal\Profile;
use App\Livewire\Members\ProfilePhoto;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function photoMember(string $firstName = 'Anna', string $surname = 'Huber'): Member
{
    return Member::create(['gender' => 'w', 'name' => $firstName, 'surname' => $surname, 'active' => 1]);
}

describe('storage', function () {
    it('stores a square 400 px jpeg and replaces the previous photo', function () {
        $member = photoMember();
        $service = app(MemberPhotoService::class);

        $service->store($member, UploadedFile::fake()->image('hochformat.png', 300, 600));
        $firstPath = $member->fresh()->photo_path;

        Storage::disk('local')->assertExists($firstPath);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('local')->get($firstPath));

        expect([$width, $height, $type])->toBe([400, 400, IMAGETYPE_JPEG])
            ->and($firstPath)->toStartWith('member-photos/'.$member->memberID.'/');

        $service->store($member->fresh(), UploadedFile::fake()->image('neu.jpg', 400, 400));

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($member->fresh()->photo_path);
    });

    it('removes the photo and its file', function () {
        $member = photoMember();
        $service = app(MemberPhotoService::class);
        $service->store($member, UploadedFile::fake()->image('foto.jpg'));
        $path = $member->fresh()->photo_path;

        $service->remove($member->fresh());

        Storage::disk('local')->assertMissing($path);
        expect($member->fresh()->photo_path)->toBeNull();
    });
});

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        $this->member = photoMember();
    });

    it('shows the initials until a photo is uploaded', function () {
        Livewire::test(MembersShow::class, ['member' => $this->member])
            ->assertSeeLivewire(ProfilePhoto::class);

        Livewire::test(ProfilePhoto::class, ['member' => $this->member, 'context' => 'staff'])
            ->assertSee('AH')
            ->assertDontSee('/photo?v=', false)
            ->set('photo', UploadedFile::fake()->image('profil.jpg', 400, 400))
            ->assertHasNoErrors()
            ->assertSee('Profilbild wurde gespeichert.')
            ->assertSee(route('members.photo', $this->member), false);

        expect($this->member->fresh()->hasPhoto())->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'member.photo_updated')->exists())->toBeTrue();
    });

    it('rejects files that are no images', function () {
        Livewire::test(ProfilePhoto::class, ['member' => $this->member, 'context' => 'staff'])
            ->set('photo', UploadedFile::fake()->create('lebenslauf.pdf', 50, 'application/pdf'))
            ->assertHasErrors('photo');

        expect($this->member->fresh()->photo_path)->toBeNull();
    });

    it('removes the photo', function () {
        app(MemberPhotoService::class)->store($this->member, UploadedFile::fake()->image('foto.jpg'));

        Livewire::test(ProfilePhoto::class, ['member' => $this->member->fresh(), 'context' => 'staff'])
            ->call('removePhoto')
            ->assertSee('Profilbild wurde entfernt.');

        expect($this->member->fresh()->photo_path)->toBeNull()
            ->and(ActivityLog::query()->where('action', 'member.photo_removed')->exists())->toBeTrue();
    });

    it('serves the photo only to signed-in officials', function () {
        app(MemberPhotoService::class)->store($this->member, UploadedFile::fake()->image('foto.jpg'));

        $this->get(route('members.photo', $this->member))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        auth()->logout();

        $this->get(route('members.photo', $this->member))->assertRedirect(route('login'));
    });

    it('answers 404 without a photo', function () {
        $this->get(route('members.photo', $this->member))->assertNotFound();
    });

    it('shows the photo of the linked member in the sidebar', function () {
        $staffMember = photoMember('Max', 'Obmann');
        app(MemberPhotoService::class)->store($staffMember, UploadedFile::fake()->image('foto.jpg'));
        $this->actingAs(User::factory()->create(['memberID' => $staffMember->memberID]));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('members.photo', $staffMember), false);
    });
});

describe('member portal', function () {
    it('lets members upload their own photo and shows it in the sidebar', function () {
        $member = loginPortalMember();

        Livewire::test(Profile::class)->assertSeeLivewire(ProfilePhoto::class);

        Livewire::test(ProfilePhoto::class, ['context' => 'portal'])
            ->set('photo', UploadedFile::fake()->image('ich.jpg', 400, 400))
            ->assertHasNoErrors();

        expect($member->fresh()->hasPhoto())->toBeTrue()
            ->and(ActivityLog::query()->where('action', 'portal.photo_updated')->sole()->memberAccountID)->not->toBeNull();

        $this->get(route('member.photo'))->assertOk();
        $this->get(route('member.profile'))->assertSee(route('member.photo'), false);
    });

    it('ignores a foreign member passed into the portal component', function () {
        $member = loginPortalMember();
        $foreign = photoMember('Fremd', 'Person');

        Livewire::test(ProfilePhoto::class, ['member' => $foreign, 'context' => 'portal'])
            ->set('photo', UploadedFile::fake()->image('ich.jpg', 400, 400));

        expect($member->fresh()->hasPhoto())->toBeTrue()
            ->and($foreign->fresh()->hasPhoto())->toBeFalse();
    });
});

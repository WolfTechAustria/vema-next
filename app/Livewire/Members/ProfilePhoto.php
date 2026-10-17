<?php

namespace App\Livewire\Members;

use App\Models\Member;
use App\Services\ActiveMemberResolver;
use App\Services\ActivityLogger;
use App\Services\MemberPhotoService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Profilbild anzeigen, zuschneiden und hochladen — auf der Mitgliedsseite
 * (Funktionäre, context „staff“) und unter „Mein Profil“ (Mitglied selbst,
 * context „portal“).
 */
class ProfilePhoto extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $memberID = null;

    #[Locked]
    public string $context = 'staff';

    /**
     * Zugeschnittenes Bild aus dem Browser (400×400 JPEG).
     *
     * @var mixed
     */
    public $photo = null;

    public ?string $statusMessage = null;

    public function mount(?Member $member = null, string $context = 'staff'): void
    {
        $this->context = $context === 'portal' ? 'portal' : 'staff';

        // Im Mitgliederbereich gilt immer das angemeldete Mitglied.
        $this->memberID = $this->context === 'portal'
            ? app(ActiveMemberResolver::class)->resolve()->memberID
            : $member?->memberID;

        abort_unless($this->memberID, 404);
    }

    public function updatedPhoto(MemberPhotoService $photos): void
    {
        $this->validate([
            'photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['photo' => 'Profilbild']);

        $member = $this->member();

        try {
            $photos->store($member, $this->photo);
        } catch (RuntimeException $exception) {
            $this->addError('photo', $exception->getMessage());

            return;
        } finally {
            $this->photo = null;
        }

        $this->log('photo_updated', 'hat ein neues Profilbild', $member);
        $this->statusMessage = 'Profilbild wurde gespeichert.';
    }

    public function removePhoto(MemberPhotoService $photos): void
    {
        $member = $this->member();

        if (! $member->hasPhoto()) {
            return;
        }

        $photos->remove($member);

        $this->log('photo_removed', 'hat kein Profilbild mehr', $member);
        $this->statusMessage = 'Profilbild wurde entfernt.';
    }

    private function member(): Member
    {
        return $this->context === 'portal'
            ? app(ActiveMemberResolver::class)->resolve()
            : Member::query()->findOrFail($this->memberID);
    }

    private function log(string $action, string $text, Member $member): void
    {
        $isPortal = $this->context === 'portal';

        ActivityLogger::log(
            ($isPortal ? 'portal.' : 'member.').$action,
            'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') '.$text
                .($isPortal ? ' (im Mitgliederbereich geändert).' : '.'),
            'Member',
            $member->memberID
        );
    }

    public function render(MemberPhotoService $photos): View
    {
        $member = $this->member();

        return view('livewire.members.profile-photo', [
            'member' => $member,
            'photoUrl' => $photos->url($member, $this->context),
        ]);
    }
}

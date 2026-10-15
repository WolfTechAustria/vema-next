<?php

use App\Livewire\MemberPortal\MyDocuments;
use App\Livewire\Members\Files;
use App\Livewire\Members\Show as MembersShow;
use App\Models\ActivityLog;
use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Models\CircularRecipient;
use App\Models\Member;
use App\Models\MemberFile;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeePrescription;
use App\Models\MembershipFeeYear;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function storedMemberFile(Member $member, array $attributes = []): MemberFile
{
    $path = 'members/'.$member->memberID.'/'.uniqid().'.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 Beitrittserklärung');

    return MemberFile::create([
        'memberID' => $member->memberID,
        'name' => 'Beitrittserklärung.pdf',
        'original_name' => 'Beitrittserklärung.pdf',
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        ...$attributes,
    ]);
}

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        $this->member = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => 'Huber', 'active' => 1]);
    });

    it('shows the file area on the member page', function () {
        Livewire::test(MembersShow::class, ['member' => $this->member])
            ->assertSeeLivewire(Files::class);
    });

    it('uploads several files with category, note and portal visibility', function () {
        Livewire::test(Files::class, ['member' => $this->member])
            ->set('newFiles', [UploadedFile::fake()->create('Beitrittserklärung.pdf', 120, 'application/pdf')])
            ->set('newFiles', [UploadedFile::fake()->image('Foto.jpg')])
            ->assertCount('pendingFiles', 2)
            ->set('uploadCategory', 'Beitrittserklärung')
            ->set('uploadNote', 'Original im Ordner')
            ->set('uploadVisible', true)
            ->call('uploadFiles')
            ->assertHasNoErrors()
            ->assertSet('pendingFiles', [])
            ->assertSee('Beitrittserklärung.pdf')
            ->assertSee('Foto.jpg');

        $files = $this->member->files()->get();

        expect($files)->toHaveCount(2)
            ->and($files->pluck('category')->unique()->all())->toBe(['Beitrittserklärung'])
            ->and($files->every(fn (MemberFile $file) => $file->visible_to_member))->toBeTrue();

        $files->each(fn (MemberFile $file) => Storage::disk('local')->assertExists($file->file_path));

        expect(ActivityLog::query()->where('action', 'member.file_uploaded')->sole()->description)
            ->toContain('2 Dateien wurden bei Mitglied "Anna Huber"');
    });

    it('rejects unsupported and too large files', function (UploadedFile $file) {
        Livewire::test(Files::class, ['member' => $this->member])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles.0')
            ->assertCount('pendingFiles', 0);
    })->with([
        'program' => fn () => UploadedFile::fake()->create('virus.exe', 10),
        'too large' => fn () => UploadedFile::fake()->create('scan.pdf', 11000, 'application/pdf'),
    ]);

    it('renames, recategorises and releases a file', function () {
        $file = storedMemberFile($this->member);

        Livewire::test(Files::class, ['member' => $this->member])
            ->call('editFile', $file->fileID)
            ->set('editName', 'Beitrittserklärung 2026')
            ->set('editCategory', 'Vereinbarung')
            ->set('editVisible', true)
            ->call('saveFile')
            ->assertHasNoErrors()
            ->assertSet('editingFileID', null);

        expect($file->fresh())
            ->name->toBe('Beitrittserklärung 2026')
            ->category->toBe('Vereinbarung')
            ->visible_to_member->toBeTrue();

        expect(loggedChanges('member.file_updated'))->toHaveKeys(['Name', 'Kategorie', 'Für das Mitglied sichtbar']);
    });

    it('deletes a file from disk and database', function () {
        $file = storedMemberFile($this->member);

        Livewire::test(Files::class, ['member' => $this->member])
            ->call('deleteFile', $file->fileID);

        Storage::disk('local')->assertMissing($file->file_path);
        expect(MemberFile::query()->count())->toBe(0)
            ->and(ActivityLog::query()->where('action', 'member.file_deleted')->exists())->toBeTrue();
    });

    it('cannot touch files of another member', function () {
        $other = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $foreignFile = storedMemberFile($other);

        expect(fn () => Livewire::test(Files::class, ['member' => $this->member])->call('deleteFile', $foreignFile->fileID))
            ->toThrow(ModelNotFoundException::class);

        Storage::disk('local')->assertExists($foreignFile->file_path);
    });

    it('serves files inline and as download with the display name', function () {
        $file = storedMemberFile($this->member, ['name' => 'Vereinbarung Standnutzung']);

        $this->get(route('member-files.show', $file))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('member-files.show', [$file, 'download' => 1]))
            ->assertOk()
            ->assertDownload('Vereinbarung Standnutzung.pdf');
    });

    it('lists fee letters and circulars with attachments as folders', function () {
        $year = MembershipFeeYear::create(['year' => 2026, 'name' => 'Mitgliedsbeitrag 2026', 'default_amount' => 25, 'active' => true]);
        $entry = MembershipFeeEntry::create(['yearID' => $year->yearID, 'memberID' => $this->member->memberID, 'amount' => 25, 'status' => 'open']);
        MembershipFeePrescription::create([
            'entryID' => $entry->entryID,
            'memberID' => $this->member->memberID,
            'yearID' => $year->yearID,
            'type' => 'reminder',
            'reminder_level' => 2,
            'file_path' => 'x.pdf',
            'file_name' => 'x.pdf',
            'sent_at' => now(),
        ]);

        $circular = Circular::create(['title' => 'Einladung JHV', 'body_html' => '<p>Hallo</p>', 'status' => 'sent']);
        CircularAttachment::create(['circularID' => $circular->circularID, 'file_name' => 'Tagesordnung.pdf', 'file_path' => 'circulars/1/t.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1000]);
        CircularRecipient::create(['circularID' => $circular->circularID, 'memberID' => $this->member->memberID, 'delivery_method' => 'email', 'email' => 'anna@example.com', 'sent_at' => now()]);

        storedMemberFile($this->member, ['name' => 'Vereinbarung']);

        // Standard: „Alle“ zeigt sämtliche Dateien mit ihrem Ordner.
        Livewire::test(Files::class, ['member' => $this->member])
            ->assertSet('folder', 'all')
            ->assertSee('Vereinbarung')
            ->assertSee('2. Erinnerung 2026')
            ->assertSee('Tagesordnung.pdf')
            ->assertSee('Beitragsvorschreibungen')
            ->call('openFolder', 'documents')
            ->assertSee('Vereinbarung')
            ->assertDontSee('2. Erinnerung 2026')
            ->call('openFolder', 'fees')
            ->assertSee('2. Erinnerung 2026')
            ->call('openFolder', 'circulars')
            ->assertSee('Rundschreiben „Einladung JHV“')
            ->assertSee('Tagesordnung.pdf')
            ->set('search', 'tagesordnung')
            ->assertSee('Tagesordnung.pdf')
            ->assertDontSee('Rundschreiben „Einladung JHV“');
    });
});

describe('member portal', function () {
    it('shows only released documents of the member', function () {
        $member = loginPortalMember();
        storedMemberFile($member, ['name' => 'Freigegeben', 'visible_to_member' => true]);
        storedMemberFile($member, ['name' => 'Intern', 'visible_to_member' => false]);

        Livewire::test(MyDocuments::class)
            ->assertSee('Freigegeben')
            ->assertDontSee('Intern');
    });

    it('only delivers released files of the logged-in member', function () {
        $member = loginPortalMember();
        $released = storedMemberFile($member, ['visible_to_member' => true]);
        $internal = storedMemberFile($member, ['visible_to_member' => false]);
        $other = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Fremd', 'active' => 1]);
        $foreign = storedMemberFile($other, ['visible_to_member' => true]);

        $this->get(route('member.documents.file', $released))->assertOk();
        $this->get(route('member.documents.file', $internal))->assertNotFound();
        $this->get(route('member.documents.file', $foreign))->assertNotFound();
    });
});

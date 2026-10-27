<?php

namespace App\Livewire\Federation;

use App\Mail\FederationMessageMail;
use App\Models\FederationMembership;
use App\Models\Tenant;
use App\Services\DemoMode;
use App\Services\FederationStorage;
use App\Services\MailQuota;
use App\Services\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * Verbandszentrale: Mitteilungen an alle Mitgliedsvereine. Die Vereine sehen
 * sie unter „Verband“; per E-Mail geht ein Hinweis an die Kontaktadresse.
 */
class Messages extends Component
{
    use WithFileUploads;

    public string $subject = '';

    public string $body = '';

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        $this->federation();
    }

    public function send(DemoMode $demoMode, MailQuota $mailQuota, FederationStorage $storage): void
    {
        $this->authorizeAdmin();

        $validated = $this->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ], [
            'subject.required' => 'Bitte einen Betreff eingeben.',
            'body.required' => 'Bitte einen Text eingeben.',
        ]);

        if ($demoMode->isActive()) {
            session()->flash('error', 'Im Testmodus können keine Mitteilungen an Vereine gesendet werden.');

            return;
        }

        $federation = $this->federation();
        $clubs = $federation->clubMemberships()->active()->with('club')->get()->map(fn (FederationMembership $membership) => $membership->club);

        if ($clubs->isEmpty()) {
            session()->flash('error', 'Es gibt noch keine Mitgliedsvereine.');

            return;
        }

        $mailRecipients = $clubs->filter(fn (Tenant $club) => filled($club->contact_email));
        $mailQuota->ensureAvailable($mailRecipients->count());

        $message = $federation->federationMessages()->create([
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'sent_by' => auth()->user()->username,
            'recipients_count' => $clubs->count(),
        ]);

        foreach ($this->attachments as $file) {
            $storage->store($federation, $file, 'federation/messages/'.$message->id, $file->getClientOriginalName(), $message);
        }

        $failed = 0;

        foreach ($mailRecipients as $club) {
            try {
                Mail::to($club->contact_email)->send(new FederationMessageMail($federation, $club, $message));
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->reset(['subject', 'body', 'attachments']);

        session()->flash(
            $failed === 0 ? 'success' : 'warning',
            'Mitteilung an '.$clubs->count().' '.($clubs->count() === 1 ? 'Verein' : 'Vereine').' veröffentlicht.'
                .($failed > 0 ? " {$failed} E-Mail-Hinweise konnten nicht gesendet werden." : ''),
        );
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function deleteMessage(int $messageId): void
    {
        $this->authorizeAdmin();

        $message = $this->federation()->federationMessages()->findOrFail($messageId);

        // Einzeln löschen, damit auch die Dateien verschwinden.
        $message->attachments->each->delete();
        $message->delete();

        session()->flash('success', 'Mitteilung „'.$message->subject.'“ gelöscht.');
    }

    private function federation(): Tenant
    {
        $tenant = app(TenantManager::class)->current();

        abort_unless($tenant?->isFederation(), 404);

        return $tenant;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    public function render()
    {
        return view('livewire.federation.messages', [
            'messages' => $this->federation()->federationMessages()->with('attachments')->latest('id')->limit(50)->get(),
            'isAdmin' => (bool) auth()->user()?->hasRole('admin'),
        ])
            ->layout('layouts.app', [
                'title' => 'Mitteilungen | Verbandszentrale | VEMA',
                'heading' => 'Verbandszentrale',
            ]);
    }
}

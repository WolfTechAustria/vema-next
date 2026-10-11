<?php

namespace App\Livewire\Circulars;

use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Models\CircularRecipient;
use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\RecipientGroup;
use App\Models\Template;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public array $existingAttachments = [];

    public string $recipientSearch = '';

    public ?int $editingCircularID = null;

    public string $recipientEmailFilter = 'all';

    public array $selectedRecipients = [];

    public array $selectedExternalRecipients = [];

    public array $attachments = [];

    public bool $showCreateDialog = false;

    public string $title = '';

    public ?int $templateID = null;

    public string $subject = '';

    public string $bodyHtml = '';

    public function createCircular(): void
    {
        $validated = $this->validate([
            'title' => [
                'required',
                'string',
                'max:200',
            ],

            'templateID' => [
                'nullable',
                'exists:tb_templates,templateID',
            ],

            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],

            'bodyHtml' => [
                'nullable',
                'string',
            ],

            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            ],
        ]);

        $before = null;

        if ($this->editingCircularID) {

            $circular = Circular::findOrFail(
                $this->editingCircularID
            );

            $before = $this->circularSnapshot($circular);

            $circular->update([
                'title' => $validated['title'],
                'templateID' => $validated['templateID'] ?? null,
                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['bodyHtml'] ?? '',
            ]);

            $circular->recipients()->delete();

        } else {

            $circular = Circular::create([
                'title' => $validated['title'],
                'templateID' => $validated['templateID'] ?? null,
                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['bodyHtml'] ?? '',
                'status' => 'draft',
            ]);
        }

        /*
         * Mitglieder als Empfänger speichern
         */
        foreach ($this->selectedRecipients as $memberID) {

            $member = Member::query()
                ->with('emails')
                ->findOrFail((int) $memberID);

            $email = $member->emails
                ->first()?->email;

            CircularRecipient::create([
                'circularID' => $circular->circularID,
                'memberID' => (int) $memberID,
                'externalContactID' => null,
                'delivery_method' => $email ? 'email' : 'post',
                'email' => $email,
            ]);
        }

        /*
         * Externe Kontakte als Empfänger speichern
         */
        foreach ($this->selectedExternalRecipients as $externalContactID) {

            $contact = ExternalContact::query()
                ->where('active', true)
                ->findOrFail((int) $externalContactID);

            CircularRecipient::create([
                'circularID' => $circular->circularID,
                'memberID' => null,
                'externalContactID' => $contact->externalContactID,
                'delivery_method' => 'email',
                'email' => $contact->email,
            ]);
        }

        /*
         * Anhänge speichern
         */
        foreach ($this->attachments as $file) {

            $originalName = $file->getClientOriginalName();

            $path = $file->store(
                'circulars/'.$circular->circularID,
                'local'
            );

            CircularAttachment::create([
                'circularID' => $circular->circularID,
                'file_name' => $originalName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        $after = $this->circularSnapshot($circular->fresh());

        if ($before === null) {
            ActivityLogger::log(
                'circular.created',
                'Rundschreiben „'.$circular->title.'“ wurde als Entwurf angelegt.',
                'Circular',
                $circular->circularID,
                ActivityLogger::created($after, self::ACTIVITY_LABELS, self::ACTIVITY_MASKED)
            );
        } else {
            $changes = ActivityLogger::changes($before, $after, self::ACTIVITY_LABELS, self::ACTIVITY_MASKED);

            if ($changes !== []) {
                ActivityLogger::log(
                    'circular.updated',
                    'Rundschreiben „'.$circular->title.'“ wurde geändert.',
                    'Circular',
                    $circular->circularID,
                    $changes
                );
            }
        }

        $this->reset([
            'showCreateDialog',
            'title',
            'templateID',
            'subject',
            'bodyHtml',
            'attachments',
            'selectedRecipients',
            'selectedExternalRecipients',
            'editingCircularID',
            'existingAttachments',
        ]);

        session()->flash(
            'success',
            'Rundschreiben wurde als Entwurf angelegt.'
        );
    }

    public function updatedTemplateID(): void
    {
        if (! $this->templateID) {
            $this->subject = '';
            $this->bodyHtml = '';

            return;
        }

        $template = Template::findOrFail(
            $this->templateID
        );

        $this->subject = $template->subject ?? '';
        $this->bodyHtml = $template->body_html ?? '';
    }

    public function removeAttachment(int $index): void
    {
        if (! isset($this->attachments[$index])) {
            return;
        }

        unset($this->attachments[$index]);

        $this->attachments = array_values(
            $this->attachments
        );
    }

    public function selectAllFilteredRecipients(): void
    {
        $members = Member::query()
            ->with('emails')
            ->where('active', 1)
            ->when(
                $this->recipientSearch,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%'.$this->recipientSearch.'%')
                            ->orWhere('surname', 'like', '%'.$this->recipientSearch.'%');
                    });
                }
            )
            ->when(
                $this->recipientEmailFilter === 'with_email',
                fn ($query) => $query->whereHas('emails')
            )
            ->when(
                $this->recipientEmailFilter === 'without_email',
                fn ($query) => $query->whereDoesntHave('emails')
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $this->selectedRecipients = $members
            ->pluck('memberID')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function clearRecipientSelection(): void
    {
        $this->selectedRecipients = [];
        $this->selectedExternalRecipients = [];
    }

    public function addRecipientGroup(int $groupID): void
    {
        $group = RecipientGroup::query()
            ->with([
                'members',
                'externalContacts',
            ])
            ->findOrFail($groupID);

        /*
         * Mitglieder übernehmen
         */
        $memberIds = $group->members
            ->pluck('memberID')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selectedRecipients = array_values(
            array_unique([
                ...$this->selectedRecipients,
                ...$memberIds,
            ])
        );

        /*
         * Externe Kontakte übernehmen
         */
        $externalContactIds = $group->externalContacts
            ->pluck('externalContactID')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selectedExternalRecipients = array_values(
            array_unique([
                ...$this->selectedExternalRecipients,
                ...$externalContactIds,
            ])
        );
    }

    public function editCircular(int $circularID): void
    {
        $circular = Circular::query()
            ->with([
                'recipients',
                'attachments',
            ])
            ->findOrFail($circularID);

        $this->existingAttachments = $circular->attachments
            ->map(fn ($attachment) => [
                'attachmentID' => $attachment->attachmentID,
                'file_name' => $attachment->file_name,
                'file_size' => $attachment->file_size,
            ])
            ->all();

        $this->editingCircularID = $circular->circularID;

        $this->title = $circular->title;
        $this->templateID = $circular->templateID;
        $this->subject = $circular->subject ?? '';
        $this->bodyHtml = $circular->body_html ?? '';

        /*
         * Mitglieder laden
         */
        $this->selectedRecipients = $circular->recipients
            ->whereNotNull('memberID')
            ->pluck('memberID')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        /*
         * Externe Kontakte laden
         */
        $this->selectedExternalRecipients = $circular->recipients
            ->whereNotNull('externalContactID')
            ->pluck('externalContactID')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $this->showCreateDialog = true;
    }

    public function removeExistingAttachment(int $attachmentID): void
    {
        $attachment = CircularAttachment::findOrFail($attachmentID);
        $circular = Circular::find($attachment->circularID);
        $before = $circular ? $this->circularSnapshot($circular) : null;

        if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $attachment->delete();

        if ($circular) {
            ActivityLogger::log(
                'circular.updated',
                'Anhang „'.$attachment->file_name.'“ wurde aus dem Rundschreiben „'.$circular->title.'“ entfernt.',
                'Circular',
                $circular->circularID,
                ActivityLogger::changes($before, $this->circularSnapshot($circular->fresh()), self::ACTIVITY_LABELS, self::ACTIVITY_MASKED)
            );
        }

        $this->existingAttachments = array_values(
            array_filter(
                $this->existingAttachments,
                fn ($item) => (int) $item['attachmentID'] !== $attachmentID
            )
        );
    }

    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'title' => 'Titel',
        'template' => 'Template',
        'subject' => 'Betreff',
        'body_html' => 'Text',
        'recipients' => 'Empfänger',
        'attachments' => 'Anhänge',
    ];

    /**
     * Lange Texte erscheinen im Protokoll nur als „geändert“.
     *
     * @var list<string>
     */
    private const ACTIVITY_MASKED = ['body_html'];

    /**
     * Empfänger als Anzahl plus sortierte Namensliste, damit auch der
     * Austausch einzelner Empfänger erkannt wird.
     *
     * @return array{title: string, template: ?string, subject: ?string, body_html: ?string, recipients: ?string, attachments: list<string>}
     */
    private function circularSnapshot(Circular $circular): array
    {
        $circular->load([
            'template',
            'recipients.member',
            'recipients.externalContact',
            'attachments',
        ]);

        $recipientNames = $circular->recipients
            ->map(fn ($recipient) => $recipient->display_name)
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'title' => $circular->title,
            'template' => $circular->template?->name,
            'subject' => $circular->subject,
            'body_html' => $circular->body_html,
            'recipients' => $recipientNames->isEmpty()
                ? null
                : $recipientNames->count().' ('.$recipientNames->implode(', ').')',
            'attachments' => $circular->attachments->pluck('file_name')->all(),
        ];
    }

    public function render()
    {
        $templates = Template::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $circulars = Circular::query()
            ->with('recipients')
            ->withCount('recipients')
            ->withCount([
                'recipients as post_recipients_count' => fn ($query) => $query->where('delivery_method', 'post'),

                'recipients as open_post_recipients_count' => fn ($query) => $query
                    ->where('delivery_method', 'post')
                    ->whereNull('sent_at'),

                'recipients as open_email_recipients_count' => fn ($query) => $query
                    ->where('delivery_method', 'email')
                    ->whereNotNull('email')
                    ->whereNull('sent_at'),
            ])
            ->latest('created_at')
            ->get();

        $recipientGroups = RecipientGroup::query()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $members = Member::query()
            ->with('emails')
            ->where('active', 1)
            ->when(
                $this->recipientSearch,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%'.$this->recipientSearch.'%')
                            ->orWhere('surname', 'like', '%'.$this->recipientSearch.'%');
                    });
                }
            )
            ->when(
                $this->recipientEmailFilter === 'with_email',
                fn ($query) => $query->whereHas('emails')
            )
            ->when(
                $this->recipientEmailFilter === 'without_email',
                fn ($query) => $query->whereDoesntHave('emails')
            )
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $externalContacts = ExternalContact::query()
            ->where('active', true)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.circulars.index',
            [
                'circulars' => $circulars,
                'templates' => $templates,
                'members' => $members,
                'recipientGroups' => $recipientGroups,
                'externalContacts' => $externalContacts,
            ]
        )->layout('layouts.app', [
            'title' => 'Rundschreiben | VEMA',
            'heading' => 'Rundschreiben',
        ]);

    }
}

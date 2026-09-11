<?php

namespace App\Livewire\Circulars;

use App\Models\Circular;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\CircularAttachment;
use App\Models\CircularRecipient;
use Illuminate\Support\Facades\Storage;

class Index extends Component
{

    use WithFileUploads;

    public array $existingAttachments = [];
    public string $recipientSearch = '';
    public ?int $editingCircularID = null;

    public string $recipientEmailFilter = 'all';

    public array $selectedRecipients = [];

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

        if ($this->editingCircularID) {

            $circular = Circular::findOrFail(
                $this->editingCircularID
            );

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

        foreach ($this->selectedRecipients as $memberID) {

            $member = \App\Models\Member::query()
                ->with('emails')
                ->findOrFail((int) $memberID);

            $email = $member->emails
                ->first()?->email;

            CircularRecipient::create([
                'circularID' => $circular->circularID,
                'memberID' => (int) $memberID,
                'delivery_method' => $email ? 'email' : 'post',
                'email' => $email,
            ]);
        }

        foreach ($this->attachments as $file) {

            $originalName = $file->getClientOriginalName();

            $path = $file->store(
                'circulars/' . $circular->circularID,
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

        $this->reset([
            'showCreateDialog',
            'title',
            'templateID',
            'subject',
            'bodyHtml',
            'attachments',
            'selectedRecipients',
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
        if (!$this->templateID) {
            $this->subject = '';
            $this->bodyHtml = '';

            return;
        }

        $template = \App\Models\Template::findOrFail(
            $this->templateID
        );

        $this->subject = $template->subject ?? '';
        $this->bodyHtml = $template->body_html ?? '';
    }
    public function removeAttachment(int $index): void
    {
        if (!isset($this->attachments[$index])) {
            return;
        }

        unset($this->attachments[$index]);

        $this->attachments = array_values(
            $this->attachments
        );
    }

    public function selectAllFilteredRecipients(): void
    {
        $members = \App\Models\Member::query()
            ->with('emails')
            ->where('active', 1)
            ->when(
                $this->recipientSearch,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%' . $this->recipientSearch . '%')
                            ->orWhere('surname', 'like', '%' . $this->recipientSearch . '%');
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
    }

    public function addRecipientGroup(int $groupID): void
    {
        $group = \App\Models\RecipientGroup::query()
            ->with('members')
            ->findOrFail($groupID);

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
    }

    public function editCircular(int $circularID): void
    {
        $circular = \App\Models\Circular::query()
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

        $this->selectedRecipients = $circular->recipients
            ->pluck('memberID')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->showCreateDialog = true;
    }

    public function removeExistingAttachment(int $attachmentID): void
    {
        $attachment = CircularAttachment::findOrFail($attachmentID);

        if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        $attachment->delete();

        $this->existingAttachments = array_values(
            array_filter(
                $this->existingAttachments,
                fn ($item) => (int) $item['attachmentID'] !== $attachmentID
            )
        );
    }

    public function render()
    {
        $templates = \App\Models\Template::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $circulars = Circular::query()
            ->with('recipients')
            ->withCount('recipients')
            ->withCount([
                'recipients as post_recipients_count' => fn ($query) =>
                $query->where('delivery_method', 'post'),

                'recipients as open_post_recipients_count' => fn ($query) =>
                $query
                    ->where('delivery_method', 'post')
                    ->whereNull('sent_at'),

                'recipients as open_email_recipients_count' => fn ($query) =>
                $query
                    ->where('delivery_method', 'email')
                    ->whereNotNull('email')
                    ->whereNull('sent_at'),
            ])
            ->latest('created_at')
            ->get();

        $recipientGroups = \App\Models\RecipientGroup::query()
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $members = \App\Models\Member::query()
            ->with('emails')
            ->where('active', 1)
            ->when(
                $this->recipientSearch,
                function ($query) {
                    $query->where(function ($query) {
                        $query
                            ->where('name', 'like', '%' . $this->recipientSearch . '%')
                            ->orWhere('surname', 'like', '%' . $this->recipientSearch . '%');
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

        return view(
            'livewire.circulars.index',
            [
                'circulars' => $circulars,
                'templates' => $templates,
                'members' => $members,
                'recipientGroups' => $recipientGroups,
            ]
        )->layout('layouts.app', [
            'title' => 'Rundschreiben | VEMA',
            'heading' => 'Rundschreiben',
        ]);


    }
}

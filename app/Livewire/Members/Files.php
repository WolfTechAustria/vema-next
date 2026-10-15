<?php

namespace App\Livewire\Members;

use App\Models\Member;
use App\Models\MemberFile;
use App\Services\ActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dateiablage auf der Mitgliedsseite: hochgeladene Dokumente sowie die
 * bereits vorhandenen Beitragsschreiben und Rundschreiben (samt Anhängen)
 * in einer gemeinsamen Explorer-Ansicht.
 */
class Files extends Component
{
    use WithFileUploads;

    public const FOLDERS = [
        'all' => 'Alle',
        'documents' => 'Dokumente',
        'fees' => 'Beitragsvorschreibungen',
        'circulars' => 'Rundschreiben',
    ];

    #[Locked]
    public Member $member;

    public string $folder = 'all';

    public string $layout = 'list';

    public string $search = '';

    /**
     * Zuletzt gewählte Dateien; werden in $pendingFiles gesammelt, damit
     * mehrere Auswahlen bzw. Drag & Drop nacheinander möglich sind.
     *
     * @var array<int, mixed>
     */
    public array $newFiles = [];

    /**
     * @var array<int, mixed>
     */
    public array $pendingFiles = [];

    public string $uploadCategory = '';

    public string $uploadNote = '';

    public bool $uploadVisible = false;

    public ?int $editingFileID = null;

    public string $editName = '';

    public string $editCategory = '';

    public string $editNote = '';

    public bool $editVisible = false;

    /**
     * Rückmeldung direkt in der Dateiablage (die Seitenmeldung gehört der
     * Mitgliedsseite und würde sonst doppelt erscheinen).
     */
    public ?string $statusMessage = null;

    public function mount(Member $member): void
    {
        $this->member = $member;
    }

    public function openFolder(string $folder): void
    {
        abort_unless(array_key_exists($folder, self::FOLDERS), 404);

        $this->folder = $folder;
        $this->search = '';
    }

    public function updatedNewFiles(): void
    {
        $this->validate(['newFiles.*' => $this->fileRules()], [], ['newFiles.*' => 'Datei']);

        $this->pendingFiles = [...$this->pendingFiles, ...$this->newFiles];
        $this->newFiles = [];
    }

    public function removePendingFile(int $index): void
    {
        unset($this->pendingFiles[$index]);
        $this->pendingFiles = array_values($this->pendingFiles);
    }

    public function uploadFiles(): void
    {
        $this->validate([
            'pendingFiles' => ['required', 'array', 'min:1'],
            'pendingFiles.*' => $this->fileRules(),
            'uploadCategory' => ['nullable', 'string', 'max:100'],
            'uploadNote' => ['nullable', 'string', 'max:2000'],
            'uploadVisible' => ['boolean'],
        ], [
            'pendingFiles.required' => 'Bitte zuerst Dateien auswählen.',
        ], [
            'pendingFiles.*' => 'Datei',
            'uploadCategory' => 'Kategorie',
            'uploadNote' => 'Notiz',
        ]);

        $names = [];

        foreach ($this->pendingFiles as $upload) {
            $originalName = $upload->getClientOriginalName();

            MemberFile::create([
                'memberID' => $this->member->memberID,
                'name' => $originalName,
                'original_name' => $originalName,
                'file_path' => $upload->store('members/'.$this->member->memberID, 'local'),
                'mime_type' => $upload->getMimeType(),
                'file_size' => $upload->getSize(),
                'category' => trim($this->uploadCategory) ?: null,
                'note' => trim($this->uploadNote) ?: null,
                'visible_to_member' => $this->uploadVisible,
                'uploaded_by' => Auth::id(),
            ]);

            $names[] = $originalName;
        }

        ActivityLogger::log(
            'member.file_uploaded',
            (count($names) === 1 ? 'Datei „'.$names[0].'“ wurde' : count($names).' Dateien wurden')
                .' bei Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') hochgeladen.',
            'Member',
            $this->member->memberID,
            ActivityLogger::created([
                'files' => $names,
                'category' => trim($this->uploadCategory),
                'visible_to_member' => $this->uploadVisible,
            ], [
                'files' => 'Dateien',
                'category' => 'Kategorie',
                'visible_to_member' => 'Für das Mitglied sichtbar',
            ])
        );

        $this->reset(['pendingFiles', 'newFiles', 'uploadCategory', 'uploadNote', 'uploadVisible']);

        if (! in_array($this->folder, ['all', 'documents'], true)) {
            $this->folder = 'documents';
        }

        $this->statusMessage = count($names) === 1 ? 'Datei wurde hochgeladen.' : count($names).' Dateien wurden hochgeladen.';
    }

    public function editFile(int $fileID): void
    {
        $file = $this->findFile($fileID);

        $this->editingFileID = $file->fileID;
        $this->editName = $file->name;
        $this->editCategory = $file->category ?? '';
        $this->editNote = $file->note ?? '';
        $this->editVisible = $file->visible_to_member;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingFileID', 'editName', 'editCategory', 'editNote', 'editVisible']);
        $this->resetValidation();
    }

    public function saveFile(): void
    {
        $file = $this->findFile((int) $this->editingFileID);

        $validated = $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editCategory' => ['nullable', 'string', 'max:100'],
            'editNote' => ['nullable', 'string', 'max:2000'],
            'editVisible' => ['boolean'],
        ], [], [
            'editName' => 'Name',
            'editCategory' => 'Kategorie',
            'editNote' => 'Notiz',
        ]);

        $before = $file->activitySnapshot();

        $file->update([
            'name' => trim($validated['editName']),
            'category' => trim($validated['editCategory']) ?: null,
            'note' => trim($validated['editNote']) ?: null,
            'visible_to_member' => $validated['editVisible'],
        ]);

        $changes = ActivityLogger::changes($before, $file->fresh()->activitySnapshot(), MemberFile::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'member.file_updated',
                'Datei „'.$file->name.'“ von Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde geändert.',
                'Member',
                $this->member->memberID,
                $changes
            );
        }

        $this->cancelEdit();

        $this->statusMessage = 'Datei wurde gespeichert.';
    }

    public function deleteFile(int $fileID): void
    {
        $file = $this->findFile($fileID);

        $file->deleteWithFile();

        ActivityLogger::log(
            'member.file_deleted',
            'Datei „'.$file->name.'“ von Mitglied "'.$this->member->full_name.'" (#'.$this->member->memberID.') wurde gelöscht.',
            'Member',
            $this->member->memberID
        );

        if ($this->editingFileID === $fileID) {
            $this->cancelEdit();
        }

        $this->statusMessage = 'Datei wurde gelöscht.';
    }

    /**
     * @return array<int, string>
     */
    private function fileRules(): array
    {
        return ['file', 'max:'.MemberFile::MAX_SIZE_KB, 'mimes:'.implode(',', MemberFile::ALLOWED_EXTENSIONS)];
    }

    private function findFile(int $fileID): MemberFile
    {
        return MemberFile::query()
            ->where('memberID', $this->member->memberID)
            ->findOrFail($fileID);
    }

    /**
     * Einträge eines Ordners in einheitlicher Form für die Explorer-Ansicht.
     *
     * @return Collection<int, array{key: string, name: string, meta: ?string, date: ?CarbonInterface, size: ?string, kind: string, url: string, downloadUrl: ?string, fileID: ?int, visible: bool, note: ?string}>
     */
    private function items(string $folder): Collection
    {
        return match ($folder) {
            'documents' => $this->member->files()
                ->orderByDesc('created_at')
                ->orderByDesc('fileID')
                ->get()
                ->map(fn (MemberFile $file) => [
                    'key' => 'file-'.$file->fileID,
                    'name' => $file->name,
                    'meta' => $file->category,
                    'date' => $file->created_at,
                    'size' => $file->file_size ? Number::fileSize($file->file_size, precision: 1) : null,
                    'kind' => $this->kind($file->mime_type, $file->original_name),
                    'url' => route('member-files.show', $file),
                    'downloadUrl' => route('member-files.show', [$file, 'download' => 1]),
                    'fileID' => $file->fileID,
                    'visible' => $file->visible_to_member,
                    'note' => $file->note,
                ]),

            'fees' => $this->member->membershipFeePrescriptions()
                ->with('year')
                ->latest('sent_at')
                ->get()
                ->map(fn ($prescription) => [
                    'key' => 'fee-'.$prescription->prescriptionID,
                    'name' => ($prescription->type === 'reminder'
                        ? ($prescription->reminder_level ?: 1).'. Erinnerung'
                        : 'Beitragsvorschreibung').' '.($prescription->year?->year ?? ''),
                    'meta' => $prescription->delivery_method === 'post'
                        ? 'Per Post'
                        : ($prescription->sent_to ? 'E-Mail an '.$prescription->sent_to : 'Per E-Mail'),
                    'date' => $prescription->sent_at ?? $prescription->created_at,
                    'size' => null,
                    'kind' => 'pdf',
                    'url' => route('membership-fee-prescriptions.pdf', $prescription),
                    'downloadUrl' => null,
                    'fileID' => null,
                    'visible' => $prescription->sent_at !== null,
                    'note' => null,
                ]),

            'circulars' => $this->member->circularRecipients()
                ->with('circular.attachments')
                ->latest('sent_at')
                ->get()
                ->filter(fn ($recipient) => $recipient->circular !== null)
                ->flatMap(fn ($recipient) => [
                    [
                        'key' => 'circular-'.$recipient->recipientID,
                        'name' => 'Rundschreiben „'.$recipient->circular->title.'“',
                        'meta' => $recipient->delivery_method === 'post' ? 'Per Post' : 'Per E-Mail',
                        'date' => $recipient->sent_at ?? $recipient->circular->created_at,
                        'size' => null,
                        'kind' => 'pdf',
                        'url' => route('circulars.recipient.preview', [$recipient->circular, $this->member]),
                        'downloadUrl' => null,
                        'fileID' => null,
                        'visible' => $recipient->sent_at !== null,
                        'note' => null,
                    ],
                    ...$recipient->circular->attachments->map(fn ($attachment) => [
                        'key' => 'circular-attachment-'.$recipient->recipientID.'-'.$attachment->attachmentID,
                        'name' => $attachment->file_name,
                        'meta' => 'Anhang zu „'.$recipient->circular->title.'“',
                        'date' => $recipient->sent_at ?? $recipient->circular->created_at,
                        'size' => $attachment->file_size ? Number::fileSize($attachment->file_size, precision: 1) : null,
                        'kind' => $this->kind($attachment->mime_type, $attachment->file_name),
                        'url' => route('circular-attachments.show', $attachment),
                        'downloadUrl' => null,
                        'fileID' => null,
                        'visible' => $recipient->sent_at !== null,
                        'note' => null,
                    ]),
                ])
                ->values(),

            default => collect(),
        };
    }

    /**
     * Grobe Dateiart für Symbol und Vorschau.
     */
    private function kind(?string $mimeType, ?string $fileName): string
    {
        $extension = Str::lower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return match (true) {
            $mimeType === 'application/pdf' || $extension === 'pdf' => 'pdf',
            in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) => 'image',
            in_array($extension, ['doc', 'docx', 'odt', 'txt', 'rtf'], true) => 'text',
            in_array($extension, ['xls', 'xlsx', 'ods', 'csv'], true) => 'sheet',
            default => 'other',
        };
    }

    public function render(): View
    {
        $itemsByFolder = collect(['documents', 'fees', 'circulars'])
            ->mapWithKeys(fn (string $folder) => [$folder => $this->items($folder)->map(fn (array $item) => [
                ...$item,
                'folderLabel' => self::FOLDERS[$folder],
            ])]);

        // „Alle“: sämtliche Dateien, neueste zuerst.
        $itemsByFolder['all'] = $itemsByFolder->flatten(1)
            ->sortByDesc(fn (array $item) => $item['date']?->getTimestamp() ?? 0)
            ->values();

        $items = $itemsByFolder[$this->folder] ?? collect();

        if (trim($this->search) !== '') {
            $needle = Str::lower(trim($this->search));

            $items = $items->filter(fn (array $item) => Str::contains(
                Str::lower($item['name'].' '.$item['meta'].' '.$item['note']),
                $needle
            ))->values();
        }

        return view('livewire.members.files', [
            'items' => $items,
            'folderCounts' => $itemsByFolder->map->count(),
            'categories' => MemberFile::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
        ]);
    }
}

<?php

namespace App\Livewire\RecipientGroups;

use App\Models\ExternalContact;
use App\Models\Member;
use App\Models\RecipientGroup;
use App\Services\ActivityLogger;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public string $description = '';

    public array $selectedMembers = [];

    public array $selectedExternalContacts = [];

    public ?int $editingGroupID = null;

    public function createGroup(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'selectedMembers' => [
                'array',
            ],
            'selectedExternalContacts' => [
                'array',
            ],
        ]);

        $group = RecipientGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $group->members()->sync(
            array_map('intval', $this->selectedMembers)
        );

        $group->externalContacts()->sync(
            array_map('intval', $this->selectedExternalContacts)
        );

        ActivityLogger::log(
            'group.created',
            'Empfängergruppe „'.$group->name.'“ wurde angelegt.',
            'RecipientGroup',
            $group->groupID,
            ActivityLogger::created($this->groupSnapshot($group), self::ACTIVITY_LABELS)
        );

        $this->resetForm();

        session()->flash(
            'success',
            'Empfängergruppe wurde angelegt.'
        );
    }

    public function editGroup(int $groupID): void
    {
        $group = RecipientGroup::query()
            ->with([
                'members',
                'externalContacts',
            ])
            ->findOrFail($groupID);

        $this->editingGroupID = $group->groupID;
        $this->name = $group->name;
        $this->description = $group->description ?? '';

        $this->selectedMembers = $group->members
            ->pluck('memberID')
            ->map(fn ($memberID) => (string) $memberID)
            ->values()
            ->all();

        $this->selectedExternalContacts = $group->externalContacts
            ->pluck('externalContactID')
            ->map(fn ($externalContactID) => (string) $externalContactID)
            ->values()
            ->all();

        $this->resetValidation();
    }

    public function updateGroup(): void
    {
        abort_unless(
            $this->editingGroupID,
            404
        );

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'selectedMembers' => [
                'array',
            ],
            'selectedExternalContacts' => [
                'array',
            ],
        ]);

        $group = RecipientGroup::findOrFail(
            $this->editingGroupID
        );

        $before = $this->groupSnapshot($group);

        $group->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $group->members()->sync(
            array_map('intval', $this->selectedMembers)
        );

        $group->externalContacts()->sync(
            array_map('intval', $this->selectedExternalContacts)
        );

        $changes = ActivityLogger::changes($before, $this->groupSnapshot($group->fresh()), self::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'group.updated',
                'Empfängergruppe „'.$group->name.'“ wurde geändert.',
                'RecipientGroup',
                $group->groupID,
                $changes
            );
        }

        $this->resetForm();

        session()->flash(
            'success',
            'Empfängergruppe wurde aktualisiert.'
        );
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function deleteGroup(int $groupID): void
    {
        $group = RecipientGroup::findOrFail($groupID);

        $memberCount = $group->members()->count();
        $contactCount = $group->externalContacts()->count();

        $group->members()->detach();
        $group->delete();

        ActivityLogger::log(
            'group.deleted',
            'Empfängergruppe „'.$group->name.'“ ('.$memberCount.' Mitglieder, '.$contactCount.' externe Kontakte) wurde gelöscht.',
            'RecipientGroup',
            $groupID
        );

        if ($this->editingGroupID === $groupID) {
            $this->resetForm();
        }

        session()->flash(
            'success',
            'Empfängergruppe wurde gelöscht.'
        );
    }

    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'name' => 'Name',
        'description' => 'Beschreibung',
        'members' => 'Mitglieder',
        'externalContacts' => 'Externe Kontakte',
    ];

    /**
     * @return array{name: string, description: ?string, members: list<string>, externalContacts: list<string>}
     */
    private function groupSnapshot(RecipientGroup $group): array
    {
        return [
            'name' => $group->name,
            'description' => $group->description,
            'members' => $group->members()->get()->map(fn (Member $member): string => $member->full_name)->all(),
            'externalContacts' => $group->externalContacts()->get()->map(fn (ExternalContact $contact): string => $contact->full_name)->all(),
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'description',
            'selectedMembers',
            'editingGroupID',
            'selectedExternalContacts',
        ]);
    }

    public function render()
    {
        $groups = RecipientGroup::query()
            ->with(
                'members',
                'externalContacts', )
            ->orderBy('name')
            ->get();

        $members = Member::query()
            ->where('active', 1)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $externalContacts = ExternalContact::query()
            ->where('active', true)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.recipient-groups.index',
            [
                'groups' => $groups,
                'members' => $members,
                'externalContacts' => $externalContacts,
            ]
        )->layout('layouts.app', [
            'title' => 'Empfängergruppen | VEMA',
            'heading' => 'Empfängergruppen',
        ]);
    }
}

<?php

namespace App\Livewire\RecipientGroups;

use App\Models\Member;
use App\Models\RecipientGroup;
use Livewire\Component;
use App\Models\ExternalContact;

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

        $group->members()->detach();
        $group->delete();

        if ($this->editingGroupID === $groupID) {
            $this->resetForm();
        }

        session()->flash(
            'success',
            'Empfängergruppe wurde gelöscht.'
        );
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
                'externalContacts',)
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

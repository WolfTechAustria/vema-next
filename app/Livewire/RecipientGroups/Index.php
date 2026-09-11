<?php

namespace App\Livewire\RecipientGroups;

use App\Models\Member;
use App\Models\RecipientGroup;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';
    public string $description = '';

    public array $selectedMembers = [];

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
        ]);

        $group = RecipientGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $group->members()->sync(
            array_map('intval', $this->selectedMembers)
        );

        $this->reset([
            'name',
            'description',
            'selectedMembers',
        ]);

        session()->flash(
            'success',
            'Empfängergruppe wurde angelegt.'
        );
    }

    public function deleteGroup(int $groupID): void
    {
        $group = RecipientGroup::findOrFail($groupID);

        $group->members()->detach();
        $group->delete();

        session()->flash(
            'success',
            'Empfängergruppe wurde gelöscht.'
        );
    }

    public function render()
    {
        $groups = RecipientGroup::query()
            ->with('members')
            ->orderBy('name')
            ->get();

        $members = Member::query()
            ->where('active', 1)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view(
            'livewire.recipient-groups.index',
            [
                'groups' => $groups,
                'members' => $members,
            ]
        )->layout('layouts.app', [
            'title' => 'Empfängergruppen | VEMA',
            'heading' => 'Empfängergruppen',
        ]);
    }
}

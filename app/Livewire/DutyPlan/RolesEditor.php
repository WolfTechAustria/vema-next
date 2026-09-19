<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlanRole;
use App\Models\RecipientGroup;
use App\Models\Skill;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RolesEditor extends Component
{
    #[Reactive]
    public ?int $planId = null;

    public bool $showForm = false;

    public ?int $editingRoleID = null;

    public string $name = '';
    public int $required_helpers = 1;
    public array $weekdays = [];
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?int $requiredGroupID = null;
    public int $required_group_min = 1;
    public ?int $requiredSkillID = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'required_helpers' => ['required', 'integer', 'min:1', 'max:20'],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'requiredGroupID' => ['nullable', 'integer', 'exists:tb_recipient_groups,groupID'],
            'required_group_min' => ['required', 'integer', 'min:1', 'lte:required_helpers'],
            'requiredSkillID' => ['nullable', 'integer', 'exists:tb_skills,skillID'],
        ];
    }

    public function newRole(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editRole(int $roleID): void
    {
        $role = DutyPlanRole::where('planID', $this->planId)->findOrFail($roleID);

        $this->editingRoleID = $role->roleID;
        $this->name = $role->name;
        $this->required_helpers = $role->required_helpers;
        $this->weekdays = collect(range(1, 7))
            ->filter(fn ($weekday) => $role->isActiveOnWeekday($weekday))
            ->map(fn ($weekday) => (string) $weekday)
            ->values()
            ->all();
        $this->start_time = $role->start_time ? substr($role->start_time, 0, 5) : null;
        $this->end_time = $role->end_time ? substr($role->end_time, 0, 5) : null;
        $this->requiredGroupID = $role->requiredGroupID;
        $this->required_group_min = $role->required_group_min ?: 1;
        $this->requiredSkillID = $role->requiredSkillID;
        $this->showForm = true;

        $this->resetValidation();
    }

    public function saveRole(): void
    {
        if (!$this->planId) {
            return;
        }

        $validated = $this->validate();

        $weekdayMask = 0;

        foreach ($validated['weekdays'] as $weekday) {
            $weekdayMask |= (1 << ((int) $weekday - 1));
        }

        $attributes = [
            'planID' => $this->planId,
            'name' => $validated['name'],
            'required_helpers' => $validated['required_helpers'],
            'weekday_mask' => $weekdayMask,
            'start_time' => $validated['start_time'] ?: null,
            'end_time' => $validated['end_time'] ?: null,
            'requiredGroupID' => $validated['requiredGroupID'] ?: null,
            'required_group_min' => $validated['requiredGroupID']
                ? $validated['required_group_min']
                : 1,
            'requiredSkillID' => $validated['requiredSkillID'] ?: null,
        ];

        if ($this->editingRoleID) {
            $role = DutyPlanRole::where('planID', $this->planId)
                ->findOrFail($this->editingRoleID);

            $role->update($attributes);

            // Umbenennung sofort auf bereits erzeugte Termine übertragen.
            $role->events()->update([
                'duty_name' => $validated['name'],
            ]);

            session()->flash('success', 'Dienstbezeichnung wurde gespeichert.');
        } else {
            $maxSortOrder = DutyPlanRole::where('planID', $this->planId)->max('sort_order');

            $attributes['sort_order'] = ($maxSortOrder ?? -1) + 1;
            $attributes['active'] = true;

            DutyPlanRole::create($attributes);

            session()->flash('success', 'Dienstbezeichnung wurde angelegt.');
        }

        $this->resetForm();

        $this->dispatch('duty-plan-roles-updated');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleRoleActive(int $roleID): void
    {
        $role = DutyPlanRole::where('planID', $this->planId)->findOrFail($roleID);

        $role->update(['active' => !$role->active]);

        $this->dispatch('duty-plan-roles-updated');
    }

    public function moveRoleUp(int $roleID): void
    {
        $this->swapSortOrder($roleID, -1);
    }

    public function moveRoleDown(int $roleID): void
    {
        $this->swapSortOrder($roleID, 1);
    }

    protected function swapSortOrder(int $roleID, int $direction): void
    {
        $roles = DutyPlanRole::where('planID', $this->planId)
            ->orderBy('sort_order')
            ->orderBy('roleID')
            ->get();

        $index = $roles->search(fn ($role) => $role->roleID === $roleID);

        if ($index === false) {
            return;
        }

        $swapIndex = $index + $direction;

        if ($swapIndex < 0 || $swapIndex >= $roles->count()) {
            return;
        }

        $a = $roles[$index];
        $b = $roles[$swapIndex];

        [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];

        $a->update(['sort_order' => $bOrder]);
        $b->update(['sort_order' => $aOrder]);
    }

    public function deleteRole(int $roleID): void
    {
        $role = DutyPlanRole::where('planID', $this->planId)->findOrFail($roleID);

        $eventsWithAssignments = $role->events()
            ->whereHas('assignments')
            ->count();

        if ($eventsWithAssignments > 0) {
            session()->flash(
                'error',
                'Dienstbezeichnung kann nicht gelöscht werden: '
                . $eventsWithAssignments
                . ' Termin(e) haben bereits Helfer eingeteilt. Bitte stattdessen deaktivieren.'
            );

            return;
        }

        $role->events()->delete();
        $role->delete();

        if ($this->editingRoleID === $roleID) {
            $this->resetForm();
        }

        session()->flash('success', 'Dienstbezeichnung wurde gelöscht.');

        $this->dispatch('duty-plan-roles-updated');
    }

    protected function resetForm(): void
    {
        $this->reset([
            'editingRoleID',
            'name',
            'weekdays',
            'start_time',
            'end_time',
            'requiredGroupID',
            'required_group_min',
            'requiredSkillID',
            'showForm',
        ]);

        $this->required_helpers = 1;
        $this->required_group_min = 1;
    }

    public function render()
    {
        $roles = $this->planId
            ? DutyPlanRole::with(['requiredGroup', 'requiredSkill'])
                ->where('planID', $this->planId)
                ->orderBy('sort_order')
                ->orderBy('roleID')
                ->get()
            : collect();

        return view('livewire.duty-plan.roles-editor', [
            'roles' => $roles,
            'recipientGroups' => RecipientGroup::orderBy('name')->get(),
            'skills' => Skill::where('active', true)->orderBy('name')->get(),
        ]);
    }
}

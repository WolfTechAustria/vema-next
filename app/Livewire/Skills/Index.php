<?php

namespace App\Livewire\Skills;

use App\Models\DutyPlanRole;
use App\Models\Skill;
use App\Services\ActivityLogger;
use Livewire\Component;

class Index extends Component
{
    /**
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'name' => 'Name',
        'description' => 'Beschreibung',
        'active' => 'Aktiv',
    ];

    public string $name = '';

    public string $description = '';

    public bool $active = true;

    public ?int $editingSkillID = null;

    public function createSkill(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tb_skills,name'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);

        $skill = Skill::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'active' => $validated['active'],
        ]);

        ActivityLogger::log(
            'skill.created',
            'Fähigkeit „'.$skill->name.'“ wurde angelegt.',
            'Skill',
            $skill->skillID,
            ActivityLogger::created($skill->fresh()->only(array_keys(self::ACTIVITY_LABELS)), self::ACTIVITY_LABELS)
        );

        $this->resetForm();

        session()->flash('success', 'Fähigkeit wurde angelegt.');
    }

    public function editSkill(int $skillID): void
    {
        $skill = Skill::findOrFail($skillID);

        $this->editingSkillID = $skill->skillID;
        $this->name = $skill->name;
        $this->description = $skill->description ?? '';
        $this->active = (bool) $skill->active;

        $this->resetValidation();
    }

    public function updateSkill(): void
    {
        abort_unless($this->editingSkillID, 404);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:tb_skills,name,'.$this->editingSkillID.',skillID'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);

        $skill = Skill::findOrFail($this->editingSkillID);
        $before = $skill->only(array_keys(self::ACTIVITY_LABELS));

        $skill->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'active' => $validated['active'],
        ]);

        $changes = ActivityLogger::changes(
            $before,
            $skill->fresh()->only(array_keys(self::ACTIVITY_LABELS)),
            self::ACTIVITY_LABELS
        );

        if ($changes !== []) {
            ActivityLogger::log(
                'skill.updated',
                'Fähigkeit „'.$skill->name.'“ wurde geändert.',
                'Skill',
                $skill->skillID,
                $changes
            );
        }

        $this->resetForm();

        session()->flash('success', 'Fähigkeit wurde aktualisiert.');
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function deleteSkill(int $skillID): void
    {
        $skill = Skill::findOrFail($skillID);

        $rolesUsingSkill = DutyPlanRole::where('requiredSkillID', $skillID)->count();

        if ($rolesUsingSkill > 0) {
            session()->flash(
                'error',
                'Fähigkeit kann nicht gelöscht werden: sie wird noch von '
                .$rolesUsingSkill.' Dienstbezeichnung(en) als Voraussetzung verwendet.'
            );

            return;
        }

        $assignedCount = $skill->members()->count() + $skill->externalContacts()->count();

        $skill->members()->detach();
        $skill->externalContacts()->detach();
        $skill->delete();

        ActivityLogger::log(
            'skill.deleted',
            'Fähigkeit „'.$skill->name.'“ ('.$assignedCount.' Zuordnungen) wurde gelöscht.',
            'Skill',
            $skillID
        );

        if ($this->editingSkillID === $skillID) {
            $this->resetForm();
        }

        session()->flash('success', 'Fähigkeit wurde gelöscht.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'description',
            'active',
            'editingSkillID',
        ]);

        $this->active = true;
    }

    public function render()
    {
        $skills = Skill::query()
            ->withCount(['members', 'externalContacts'])
            ->orderBy('name')
            ->get();

        return view('livewire.skills.index', [
            'skills' => $skills,
        ])->layout('layouts.app', [
            'title' => 'Fähigkeiten | VEMA',
            'heading' => 'Fähigkeiten',
        ]);
    }
}

<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanVolunteer;
use Livewire\Component;

class Absences extends Component
{
    public ?int $memberId = null;

    public string $dateFrom = '';
    public string $dateTo = '';

    public string $note = '';

    protected function rules(): array
    {
        return [
            'memberId' => ['required', 'integer'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        DutyPlanAbsence::create([
            'memberID' => $validated['memberId'],
            'date_from' => $validated['dateFrom'],
            'date_to' => $validated['dateTo'],
            'note' => $validated['note'] ?: null,
        ]);

        $this->reset([
            'memberId',
            'dateFrom',
            'dateTo',
            'note',
        ]);

        session()->flash(
            'success',
            'Abwesenheit wurde eingetragen.'
        );
    }

    public function updatedDateFrom(string $value): void
    {
        if (!$this->dateTo) {
            $this->dateTo = $value;
        }
    }

    public function delete(int $absenceId): void
    {
        DutyPlanAbsence::query()
            ->whereKey($absenceId)
            ->delete();
    }

    public function render()
    {
        $volunteers = DutyPlanVolunteer::query()
            ->with('member')
            ->whereNotNull('memberID')
            ->where('active', true)
            ->whereHas('member', fn ($query) =>
            $query->where('active', true)
            )
            ->get()
            ->unique('memberID')
            ->sortBy(fn ($volunteer) =>
                $volunteer->member?->surname
                . ' '
                . $volunteer->member?->name
            );

        $absences = DutyPlanAbsence::query()
            ->with('member')
            ->orderByDesc('date_from')
            ->get();

        return view('livewire.duty-plan.absences', [
            'volunteers' => $volunteers,
            'absences' => $absences,
        ])->layout('layouts.app', [
            'title' => 'Abwesenheiten | VEMA',
            'heading' => 'Abwesenheiten',
        ]);
    }
}

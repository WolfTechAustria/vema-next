<?php

namespace App\Livewire\DutyPlan;

use App\Models\DutyPlanAbsence;
use App\Models\DutyPlanVolunteer;
use App\Services\ActivityLogger;
use Livewire\Component;

class Absences extends Component
{
    public ?int $memberId = null;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $note = '';

    /**
     * Feldnamen für das Aktivitätsprotokoll.
     *
     * @var array<string, string>
     */
    private const ACTIVITY_LABELS = [
        'member' => 'Helfer',
        'date_from' => 'Von',
        'date_to' => 'Bis',
        'note' => 'Notiz',
    ];

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

        $absence = DutyPlanAbsence::create([
            'memberID' => $validated['memberId'],
            'date_from' => $validated['dateFrom'],
            'date_to' => $validated['dateTo'],
            'note' => $validated['note'] ?: null,
        ]);

        $absence = $absence->fresh('member');

        ActivityLogger::log(
            'duty.absence_created',
            'Abwesenheit '.$this->absenceName($absence).' wurde eingetragen.',
            'DutyPlanAbsence',
            $absence->absenceID,
            ActivityLogger::created([
                'member' => $absence->member?->full_name,
                'date_from' => $absence->date_from,
                'date_to' => $absence->date_to,
                'note' => $absence->note,
            ], self::ACTIVITY_LABELS)
        );

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
        if (! $this->dateTo) {
            $this->dateTo = $value;
        }
    }

    public function delete(int $absenceId): void
    {
        $absence = DutyPlanAbsence::query()
            ->with('member')
            ->find($absenceId);

        if (! $absence) {
            return;
        }

        $absence->delete();

        ActivityLogger::log(
            'duty.absence_deleted',
            'Abwesenheit '.$this->absenceName($absence).' wurde gelöscht.',
            'DutyPlanAbsence',
            $absence->absenceID
        );
    }

    /**
     * Z. B. von "Max Muster" (01.11.2026 – 05.11.2026).
     */
    private function absenceName(DutyPlanAbsence $absence): string
    {
        return 'von "'.$absence->member?->full_name.'" ('
            .$absence->date_from->format('d.m.Y').' – '.$absence->date_to->format('d.m.Y').')';
    }

    public function render()
    {
        $volunteers = DutyPlanVolunteer::query()
            ->with('member')
            ->whereNotNull('memberID')
            ->where('active', true)
            ->whereHas('member', fn ($query) => $query->where('active', true)
            )
            ->get()
            ->unique('memberID')
            ->sortBy(fn ($volunteer) => $volunteer->member?->surname
                .' '
                .$volunteer->member?->name
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

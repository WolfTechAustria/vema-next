<?php

namespace App\Livewire\Events;

use App\Models\ClubEvent;
use App\Services\EventAttendance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Anwesenheitsquote aller Mitglieder in einem Kalenderjahr.
 */
class AttendanceStatistics extends Component
{
    #[Url]
    public int $year = 0;

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = (int) now()->year;
        }
    }

    public function render(EventAttendance $attendance): View
    {
        $events = ClubEvent::query()
            ->whereNotNull('attendance_taken_at')
            ->whereYear('starts_at', $this->year)
            ->withCount(['attendances', 'attendances as present_count' => fn ($query) => $query->where('present', true)])
            ->orderByDesc('starts_at')
            ->get();

        return view('livewire.events.attendance-statistics', [
            'rows' => $attendance->statistics($this->year),
            'events' => $events,
            'years' => $attendance->years(),
        ])->layout('layouts.app', [
            'title' => 'Anwesenheit | VEMA',
            'heading' => 'Anwesenheit',
        ]);
    }
}

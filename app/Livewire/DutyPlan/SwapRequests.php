<?php

namespace App\Livewire\DutyPlan;

use App\Enums\DutySwapStatus;
use App\Models\DutySwapRequest;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\DutySwaps;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Diensttausche der Mitglieder: Freigabe (falls eingestellt), Verlauf und
 * Einstellungen.
 */
class SwapRequests extends Component
{
    public bool $enabled = true;

    public bool $requiresApproval = false;

    public int $deadlineHours = 0;

    public string $notifyEmails = '';

    public function mount(): void
    {
        $setting = Setting::current();

        $this->enabled = (bool) ($setting->duty_swaps_enabled ?? true);
        $this->requiresApproval = (bool) $setting->duty_swap_requires_approval;
        $this->deadlineHours = (int) $setting->duty_swap_deadline_hours;
        $this->notifyEmails = implode(', ', $setting->duty_swap_notify_emails ?? []);
    }

    public function approve(int $swapID, DutySwaps $swaps): void
    {
        try {
            $swaps->approve($this->awaitingSwap($swapID), auth()->user());
        } catch (ValidationException $exception) {
            session()->flash('error', $exception->validator->errors()->first('swap'));

            return;
        }

        session()->flash('success', 'Der Tausch wurde freigegeben und im Dienstplan übernommen.');
    }

    public function reject(int $swapID, DutySwaps $swaps): void
    {
        $swaps->reject($this->awaitingSwap($swapID), auth()->user());

        session()->flash('success', 'Der Tausch wurde abgelehnt. Die Dienste bleiben unverändert.');
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $emails = collect(preg_split('/[\s,;]+/', $this->notifyEmails) ?: [])
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->unique()
            ->values();

        $this->validate([
            'deadlineHours' => ['required', 'integer', 'min:0', 'max:720'],
        ], [], ['deadlineHours' => 'Frist']);

        $invalid = $emails->first(fn (string $email) => ! filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($invalid) {
            $this->addError('notifyEmails', '„'.$invalid.'“ ist keine gültige E-Mail-Adresse.');

            return;
        }

        $setting = Setting::current();
        $labels = [
            'duty_swaps_enabled' => 'Diensttausch aktiv',
            'duty_swap_requires_approval' => 'Freigabe durch den Verein',
            'duty_swap_deadline_hours' => 'Frist (Stunden vor Dienstbeginn)',
            'duty_swap_notify_emails' => 'Benachrichtigung an',
        ];
        $before = [
            'duty_swaps_enabled' => (bool) ($setting->duty_swaps_enabled ?? true),
            'duty_swap_requires_approval' => (bool) $setting->duty_swap_requires_approval,
            'duty_swap_deadline_hours' => (int) $setting->duty_swap_deadline_hours,
            'duty_swap_notify_emails' => implode(', ', $setting->duty_swap_notify_emails ?? []),
        ];

        $setting->update([
            'duty_swaps_enabled' => $this->enabled,
            'duty_swap_requires_approval' => $this->requiresApproval,
            'duty_swap_deadline_hours' => $this->deadlineHours,
            'duty_swap_notify_emails' => $emails->all() ?: null,
        ]);

        $this->notifyEmails = $emails->implode(', ');

        ActivityLogger::log(
            'settings.duty_swaps_updated',
            'Einstellungen zum Diensttausch wurden geändert.',
            'Setting',
            $setting->getKey(),
            ActivityLogger::changes($before, [
                'duty_swaps_enabled' => $this->enabled,
                'duty_swap_requires_approval' => $this->requiresApproval,
                'duty_swap_deadline_hours' => $this->deadlineHours,
                'duty_swap_notify_emails' => $this->notifyEmails,
            ], $labels)
        );

        session()->flash('success', 'Einstellungen gespeichert.');
    }

    private function awaitingSwap(int $swapID): DutySwapRequest
    {
        return DutySwapRequest::query()
            ->where('status', DutySwapStatus::AwaitingApproval)
            ->find($swapID) ?? abort(404);
    }

    public function render(DutySwaps $swaps)
    {
        $swaps->expireStale();

        $relations = ['requester', 'acceptedBy', 'targetMember', 'assignment.event.role', 'targetAssignment.event', 'decidedBy'];

        return view('livewire.duty-plan.swap-requests', [
            'swaps' => $swaps,
            'awaiting' => DutySwapRequest::query()
                ->with($relations)
                ->where('status', DutySwapStatus::AwaitingApproval)
                ->oldest('responded_at')
                ->get(),
            'history' => DutySwapRequest::query()
                ->with($relations)
                ->where('status', '!=', DutySwapStatus::AwaitingApproval)
                ->latest('updated_at')
                ->limit(50)
                ->get(),
            'isAdmin' => (bool) auth()->user()?->hasRole('admin'),
        ])->layout('layouts.app', [
            'title' => 'Diensttausch | VEMA',
            'heading' => 'Dienstplan',
        ]);
    }
}

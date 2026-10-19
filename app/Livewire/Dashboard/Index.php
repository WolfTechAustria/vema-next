<?php

namespace App\Livewire\Dashboard;

use App\Enums\DashboardModule;
use App\Enums\PostStatus;
use App\Models\ActivityLog;
use App\Models\CashBookYear;
use App\Models\ClubEvent;
use App\Models\DutyPlanEvent;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberMembershipPeriod;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;
use App\Models\Post;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DashboardLayout;
use App\Services\MemberHistory;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * Dashboard aus Modulen, die jeder Benutzer ein-/ausblenden und per
 * Drag & Drop anordnen kann (siehe DashboardLayout, DashboardModule).
 */
class Index extends Component
{
    public const HISTORY_RANGES = [5, 10, 20];

    public bool $editing = false;

    public int $historyYears = 10;

    public function startEditing(): void
    {
        $this->editing = true;
    }

    public function stopEditing(): void
    {
        $this->editing = false;
    }

    public function sortModule(string $key, int $position): void
    {
        $keys = array_values(array_diff($this->visibleKeys(), [$key]));

        if (count($keys) === count($this->visibleKeys())) {
            return;
        }

        array_splice($keys, max(0, min($position, count($keys))), 0, [$key]);

        $this->layout()->save($this->user(), $keys);
    }

    public function hideModule(string $key): void
    {
        $this->layout()->save($this->user(), array_values(array_diff($this->visibleKeys(), [$key])));
    }

    public function showModule(string $key): void
    {
        $this->layout()->save($this->user(), [...$this->visibleKeys(), $key]);
    }

    public function resetToDefault(): void
    {
        $this->layout()->reset($this->user());

        session()->flash('success', 'Das Dashboard wurde auf den Standard zurückgesetzt.');
    }

    public function saveAsClubDefault(): void
    {
        abort_unless($this->user()->hasRole('admin'), 403);

        $before = $this->layout()->clubDefaultKeys();
        $after = $this->layout()->saveClubDefault($this->visibleKeys());

        ActivityLogger::log(
            'settings.dashboard_default_updated',
            'Standard-Anordnung des Dashboards wurde geändert.',
            'Setting',
            null,
            // Als Text, weil changes() Listen alphabetisch sortiert und
            // eine reine Umordnung sonst nicht sichtbar wäre.
            ActivityLogger::changes(
                ['modules' => implode(', ', $this->moduleLabels($before ?? []))],
                ['modules' => implode(', ', $this->moduleLabels($after))],
                ['modules' => 'Module']
            )
        );

        session()->flash('success', 'Deine Anordnung gilt jetzt als Standard für alle Benutzer ohne eigene Anordnung.');
    }

    public function setHistoryYears(int $years): void
    {
        if (in_array($years, self::HISTORY_RANGES, true)) {
            $this->historyYears = $years;
        }
    }

    public function render()
    {
        $user = $this->user();
        $modules = $this->layout()->modulesFor($user);

        return view('livewire.dashboard.index', [
            'modules' => $modules,
            'moduleData' => collect($modules)->mapWithKeys(fn (DashboardModule $module) => [
                $module->value => $this->dataFor($module),
            ])->all(),
            'hiddenModules' => $this->editing
                ? array_values(array_filter(
                    DashboardModule::cases(),
                    fn (DashboardModule $module) => $module->isAvailableFor($user) && ! in_array($module, $modules, true)
                ))
                : [],
            'isAdmin' => $user->hasRole('admin'),
        ])->layout('layouts.app', [
            'title' => 'Dashboard | VEMA',
            'heading' => 'Dashboard',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFor(DashboardModule $module): array
    {
        return match ($module) {
            DashboardModule::MemberStats => $this->memberStats(),
            DashboardModule::MemberHistory => ['years' => app(MemberHistory::class)->years($this->historyYears)],
            DashboardModule::MembershipFees => ['stats' => $this->membershipFeeStats(), 'actions' => $this->membershipFeeActions()],
            DashboardModule::UpcomingEvents => ['events' => ClubEvent::query()->upcoming()->orderBy('starts_at')->limit(5)->get()],
            DashboardModule::UpcomingDuties => ['duties' => DutyPlanEvent::query()
                ->with('role')
                ->withCount('assignments')
                ->where('duty_date', '>=', today())
                ->orderBy('duty_date')
                ->limit(6)
                ->get()],
            DashboardModule::Birthdays => ['birthdays' => $this->upcomingBirthdays()],
            DashboardModule::CashBook => $this->cashBook(),
            DashboardModule::OpenInvoices => $this->openInvoices(),
            DashboardModule::RecentActivity => ['entries' => ActivityLog::query()
                ->with(['user', 'memberAccount'])
                ->orderByDesc('created_at')
                ->orderByDesc('activityLogID')
                ->limit(8)
                ->get()],
            DashboardModule::HomepagePosts => [
                'drafts' => Post::query()->where('status', PostStatus::Draft)->latest('updated_at')->limit(3)->get(),
                'draftCount' => Post::query()->where('status', PostStatus::Draft)->count(),
                'published' => Post::query()->published()->latest('published_at')->limit(3)->get(),
            ],
        };
    }

    /**
     * @return array{active: int, joined: int, left: int, year: int}
     */
    private function memberStats(): array
    {
        $year = (int) now()->year;

        return [
            'active' => Member::where('active', 1)->count(),
            'joined' => MemberMembershipPeriod::whereYear('date_from', $year)->count(),
            'left' => MemberMembershipPeriod::whereYear('date_to', $year)->count(),
            'year' => $year,
        ];
    }

    /**
     * Geburtstage aktiver Mitglieder in den nächsten 30 Tagen. Sortiert wird
     * in PHP, weil DAY()/DAYOFYEAR() nicht auf jeder Datenbank existieren.
     *
     * @return list<array{member: Member, date: CarbonImmutable, age: int, isRound: bool}>
     */
    private function upcomingBirthdays(): array
    {
        $today = CarbonImmutable::today();

        return Member::query()
            ->where('active', 1)
            ->whereNotNull('dateOfBirth')
            ->get()
            ->map(function (Member $member) use ($today) {
                $birthday = CarbonImmutable::parse($member->dateOfBirth)->setYear($today->year);

                if ($birthday->lt($today)) {
                    $birthday = $birthday->addYear();
                }

                $age = $birthday->year - $member->dateOfBirth->year;

                return [
                    'member' => $member,
                    'date' => $birthday,
                    'age' => $age,
                    'isRound' => $member->isRoundOrHalfRoundBirthday($age),
                ];
            })
            ->filter(fn (array $birthday) => $birthday['date']->lte($today->addDays(30)))
            ->sortBy(fn (array $birthday) => $birthday['date']->timestamp)
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array{year: ?CashBookYear, balance: float, income: float, expense: float}
     */
    private function cashBook(): array
    {
        $year = CashBookYear::current();

        if (! $year) {
            return ['year' => null, 'balance' => 0.0, 'income' => 0.0, 'expense' => 0.0];
        }

        $totals = $year->totals();

        return [
            'year' => $year,
            'balance' => $year->balance(),
            'income' => (float) $totals['income'],
            'expense' => (float) $totals['expense'],
        ];
    }

    /**
     * @return array{count: int, sum: float, overdue: int}
     */
    private function openInvoices(): array
    {
        return [
            'count' => Invoice::where('status', 'sent')->count(),
            'sum' => (float) Invoice::where('status', 'sent')->sum('total_gross'),
            'overdue' => Invoice::where('status', 'sent')->whereDate('due_date', '<', today())->count(),
        ];
    }

    /**
     * @return array{reminder_due: int, open_without_email: int}
     */
    private function membershipFeeActions(): array
    {
        $year = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->first();

        if (! $year) {
            return [
                'reminder_due' => 0,
                'open_without_email' => 0,
            ];
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member.emails',
                'year',
                'prescriptions',
            ])
            ->where('yearID', $year->yearID)
            ->where('status', 'open')
            ->whereHas('member', fn ($query) => $query->where('active', 1))
            ->get();

        return [
            'reminder_due' => $entries
                ->filter(fn ($entry) => $entry->isReminderDue())
                ->count(),

            'open_without_email' => $entries
                ->filter(fn ($entry) => $entry->member?->emails?->isEmpty())
                ->count(),
        ];
    }

    /**
     * @return array{year: ?int, open: int, paid: int, exempt: int, reminder_due: int, reminded: int, open_amount: float, paid_amount: float}
     */
    private function membershipFeeStats(): array
    {
        $year = MembershipFeeYear::query()
            ->orderByDesc('year')
            ->first();

        if (! $year) {
            return [
                'year' => null,
                'open' => 0,
                'paid' => 0,
                'exempt' => 0,
                'reminder_due' => 0,
                'reminded' => 0,
                'open_amount' => 0.0,
                'paid_amount' => 0.0,
            ];
        }

        $entries = MembershipFeeEntry::query()
            ->with([
                'member',
                'year',
                'prescriptions',
            ])
            ->where('yearID', $year->yearID)
            ->whereHas('member', fn ($query) => $query->where('active', 1))
            ->get();

        return [
            'year' => $year->year,
            'open' => $entries->where('status', 'open')->count(),
            'paid' => $entries->where('status', 'paid')->count(),
            'exempt' => $entries->where('status', 'exempt')->count(),
            'reminder_due' => $entries->filter(fn ($entry) => $entry->isReminderDue())->count(),
            'reminded' => $entries
                ->filter(fn ($entry) => $entry->prescriptions->where('type', 'reminder')->isNotEmpty())
                ->count(),
            'open_amount' => $entries->where('status', 'open')->sum(fn ($entry) => (float) ($entry->amount ?? 0)),
            'paid_amount' => $entries->where('status', 'paid')->sum(fn ($entry) => (float) ($entry->amount ?? 0)),
        ];
    }

    /**
     * @return list<string>
     */
    private function visibleKeys(): array
    {
        return array_map(
            fn (DashboardModule $module) => $module->value,
            $this->layout()->modulesFor($this->user())
        );
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function moduleLabels(array $keys): array
    {
        return array_values(array_filter(array_map(
            fn (string $key) => DashboardModule::tryFrom($key)?->label(),
            $keys
        )));
    }

    private function layout(): DashboardLayout
    {
        return app(DashboardLayout::class);
    }

    private function user(): User
    {
        return auth()->guard('web')->user();
    }
}

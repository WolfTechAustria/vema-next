<?php

namespace App\Enums;

use App\Models\User;
use App\Services\PlanEntitlements;

/**
 * Bausteine des Dashboards. Ein Modul ist nur sichtbar, wenn der Benutzer
 * auch die zugehörige Seite öffnen darf (gleiche Rollen- und Paketprüfung
 * wie die Routen-Middleware).
 */
enum DashboardModule: string
{
    case MemberStats = 'member_stats';
    case MemberHistory = 'member_history';
    case MembershipFees = 'membership_fees';
    case UpcomingEvents = 'upcoming_events';
    case UpcomingDuties = 'upcoming_duties';
    case Birthdays = 'birthdays';
    case CashBook = 'cash_book';
    case OpenInvoices = 'open_invoices';
    case RecentActivity = 'recent_activity';
    case HomepagePosts = 'homepage_posts';

    public function label(): string
    {
        return match ($this) {
            self::MemberStats => 'Mitglieder',
            self::MemberHistory => 'Mitgliederentwicklung',
            self::MembershipFees => 'Mitgliedsbeiträge',
            self::UpcomingEvents => 'Nächste Termine',
            self::UpcomingDuties => 'Nächste Dienste',
            self::Birthdays => 'Geburtstage',
            self::CashBook => 'Kassastand',
            self::OpenInvoices => 'Offene Rechnungen',
            self::RecentActivity => 'Letzte Aktivitäten',
            self::HomepagePosts => 'Homepage-Beiträge',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MemberStats => 'Aktive Mitglieder sowie Ein- und Austritte im laufenden Jahr.',
            self::MemberHistory => 'Mitgliederstand zum Jahresende mit Ein- und Austritten der letzten Jahre.',
            self::MembershipFees => 'Stand der Beitragsvorschreibungen und fällige Erinnerungen.',
            self::UpcomingEvents => 'Die nächsten Vereinstermine.',
            self::UpcomingDuties => 'Die nächsten Dienste mit ihrer Besetzung.',
            self::Birthdays => 'Geburtstage der nächsten 30 Tage, runde hervorgehoben.',
            self::CashBook => 'Kassastand des laufenden Vereinsjahres.',
            self::OpenInvoices => 'Versendete, noch nicht bezahlte Rechnungen.',
            self::RecentActivity => 'Die letzten Einträge im Aktivitätsprotokoll.',
            self::HomepagePosts => 'Entwürfe und zuletzt veröffentlichte Beiträge.',
        };
    }

    public function isWide(): bool
    {
        return in_array($this, [self::MemberHistory, self::MembershipFees, self::RecentActivity], true);
    }

    public function isAvailableFor(User $user): bool
    {
        $entitlements = app(PlanEntitlements::class);

        return match ($this) {
            self::MemberStats, self::MemberHistory, self::MembershipFees, self::Birthdays => true,
            self::UpcomingEvents => $entitlements->allows(Feature::Events),
            self::UpcomingDuties => $entitlements->allows(Feature::DutyPlan),
            self::CashBook => $user->hasAnyRole(['admin', 'kassier']),
            self::OpenInvoices => $user->hasAnyRole(['admin', 'kassier']) && $entitlements->allows(Feature::Invoices),
            self::RecentActivity => $user->hasRole('admin') && $entitlements->allows(Feature::ActivityLog),
            self::HomepagePosts => $user->hasAnyRole(['admin', 'homepage']) && $entitlements->allows(Feature::Homepage),
        };
    }

    /**
     * Anordnung, solange weder Benutzer noch Verein eine eigene gespeichert haben.
     *
     * @return list<self>
     */
    public static function defaultOrder(): array
    {
        return [
            self::MemberStats,
            self::UpcomingEvents,
            self::UpcomingDuties,
            self::Birthdays,
            self::MembershipFees,
            self::MemberHistory,
        ];
    }
}

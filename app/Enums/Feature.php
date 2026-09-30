<?php

namespace App\Enums;

/**
 * Funktionen, die je nach Paket freigeschaltet sind (nur TENANCY_MODE=multi).
 * Mitglieder, Beiträge, Kassabuch und Rundschreiben hat jedes Paket.
 */
enum Feature: string
{
    case CashBookReceipts = 'cash_book_receipts';
    case Templates = 'templates';
    case DutyPlan = 'duty_plan';
    case Invoices = 'invoices';
    case RecipientGroups = 'recipient_groups';
    case MemberPortal = 'member_portal';
    case ActivityLog = 'activity_log';
    case TestMode = 'test_mode';

    public function label(): string
    {
        return match ($this) {
            self::CashBookReceipts => 'Belegablage im Kassabuch',
            self::Templates => 'Eigene Vorlagen & Briefpapier',
            self::DutyPlan => 'Dienstplan',
            self::Invoices => 'Rechnungen',
            self::RecipientGroups => 'Empfängergruppen & externe Kontakte',
            self::MemberPortal => 'Mitgliederportal',
            self::ActivityLog => 'Aktivitätsprotokoll',
            self::TestMode => 'Testmodus',
        };
    }
}

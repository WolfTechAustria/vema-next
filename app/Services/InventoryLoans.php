<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\InventoryLoan;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Material an Mitglieder ausgeben und zurücknehmen. Ein Stück kann immer nur
 * an ein Mitglied ausgegeben sein.
 */
class InventoryLoans
{
    public function issue(InventoryItem $item, Member $member, string $issuedOn, ?string $dueOn, ?string $note, string $errorKey = 'loanMemberID'): InventoryLoan
    {
        return DB::transaction(function () use ($item, $member, $issuedOn, $dueOn, $note, $errorKey) {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->itemID);

            if ($item->currentLoan()->exists()) {
                throw ValidationException::withMessages([$errorKey => 'Dieses Material ist bereits ausgegeben.']);
            }

            if ($item->condition === InventoryCondition::Retired) {
                throw ValidationException::withMessages([$errorKey => 'Ausgeschiedenes Material kann nicht ausgegeben werden.']);
            }

            $loan = InventoryLoan::create([
                'itemID' => $item->itemID,
                'memberID' => $member->memberID,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn ?: null,
                'note' => filled($note) ? trim($note) : null,
            ]);

            $description = $this->describe($loan);

            ActivityLogger::log(
                'inventory.issued',
                'Material "'.$item->label().'" wurde an "'.$member->full_name.'" (#'.$member->memberID.') ausgegeben.',
                'InventoryItem',
                $item->itemID,
                ActivityLogger::created(['loan' => $description], ['loan' => 'Ausgabe'])
            );

            ActivityLogger::log(
                'member.inventory_issued',
                'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') erhielt Material "'.$item->label().'".',
                'Member',
                $member->memberID,
                ActivityLogger::created(['loan' => $description], ['loan' => 'Material'])
            );

            return $loan;
        });
    }

    public function return(InventoryLoan $loan, string $returnedOn, ?string $condition = null): void
    {
        DB::transaction(function () use ($loan, $returnedOn, $condition) {
            if (! $loan->isOpen()) {
                return;
            }

            $loan->update(['returned_on' => $returnedOn]);

            $item = $loan->item;
            $changes = [['field' => 'Zurückgegeben', 'old' => null, 'new' => $loan->returned_on->format('d.m.Y'), 'masked' => false]];

            if ($condition !== null && $condition !== $item->condition->value) {
                $before = $item->condition->label();
                $item->update(['condition' => $condition]);
                $changes[] = ['field' => 'Zustand', 'old' => $before, 'new' => $item->condition->label(), 'masked' => false];
            }

            $memberName = $loan->member?->full_name ?? 'Mitglied #'.$loan->memberID;

            ActivityLogger::log(
                'inventory.returned',
                'Material "'.$item->label().'" wurde von "'.$memberName.'" zurückgegeben.',
                'InventoryItem',
                $item->itemID,
                $changes
            );

            ActivityLogger::log(
                'member.inventory_returned',
                'Mitglied "'.$memberName.'" (#'.$loan->memberID.') gab Material "'.$item->label().'" zurück.',
                'Member',
                $loan->memberID,
                [$changes[0]]
            );
        });
    }

    private function describe(InventoryLoan $loan): string
    {
        return implode(', ', array_filter([
            $loan->item->label(),
            'ab '.$loan->issued_on->format('d.m.Y'),
            $loan->due_on ? 'bis '.$loan->due_on->format('d.m.Y') : null,
            $loan->note,
        ]));
    }
}

<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Enums\InventoryInspectionResult;
use App\Models\InventoryInspection;
use App\Models\InventoryItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prüfungen und Wartungen erfassen. Mit Prüfintervall wird der nächste
 * Termin automatisch berechnet, sonst kann er von Hand gesetzt werden.
 */
class InventoryInspections
{
    public function record(InventoryItem $item, string $inspectedOn, InventoryInspectionResult $result, ?string $note, ?string $nextInspectionOn = null): InventoryInspection
    {
        return DB::transaction(function () use ($item, $inspectedOn, $result, $note, $nextInspectionOn) {
            $before = $item->activitySnapshot();

            $inspection = InventoryInspection::create([
                'itemID' => $item->itemID,
                'inspected_on' => $inspectedOn,
                'result' => $result,
                'note' => filled($note) ? trim($note) : null,
                'user_id' => auth()->guard('web')->id(),
            ]);

            $next = match (true) {
                $result === InventoryInspectionResult::Retired => null,
                filled($nextInspectionOn) => $nextInspectionOn,
                $item->inspection_interval_months !== null => Carbon::parse($inspectedOn)->addMonthsNoOverflow($item->inspection_interval_months)->toDateString(),
                default => null,
            };

            $item->update(array_filter([
                'next_inspection_on' => $next,
                'condition' => match ($result) {
                    InventoryInspectionResult::Retired => InventoryCondition::Retired,
                    InventoryInspectionResult::Defects => InventoryCondition::Defective,
                    default => null,
                },
            ], fn ($value, string $key) => $key === 'next_inspection_on' || $value !== null, ARRAY_FILTER_USE_BOTH));

            ActivityLogger::log(
                'inventory.inspected',
                'Prüfung von Material "'.$item->label().'" erfasst: '.$result->label().'.',
                'InventoryItem',
                $item->itemID,
                [
                    ['field' => 'Prüfung', 'old' => null, 'new' => implode(', ', array_filter([$inspection->inspected_on->format('d.m.Y'), $result->label(), $inspection->note])), 'masked' => false],
                    ...ActivityLogger::changes($before, $item->fresh()->activitySnapshot(), InventoryItem::ACTIVITY_LABELS),
                ]
            );

            return $inspection;
        });
    }
}

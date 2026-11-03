<?php

namespace App\Services;

use App\Enums\BoardFunctionTemplate;
use App\Models\BoardFunction;
use App\Models\MemberBoardFunctionAssignment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ersetzt den Katalog der Vorstandsfunktionen durch eine Vorlage — nur
 * solange keine Funktion vergeben ist, damit keine Chronik verloren geht.
 */
class BoardFunctionTemplates
{
    public function canApply(): bool
    {
        return ! MemberBoardFunctionAssignment::query()->exists();
    }

    public function apply(BoardFunctionTemplate $template): void
    {
        if (! $this->canApply()) {
            throw new RuntimeException('Es sind bereits Vorstandsfunktionen vergeben.');
        }

        DB::transaction(function () use ($template) {
            BoardFunction::query()->delete();

            foreach ($template->functions() as $position => $function) {
                BoardFunction::create($function + [
                    'sort_order' => $position + 1,
                    'active' => true,
                ]);
            }
        });
    }
}

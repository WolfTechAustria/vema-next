<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vorstandsfunktion aus dem vereinsspezifisch pflegbaren Katalog.
 *
 * - is_top_vorstand: Spitzenfunktion (z. B. Obmann, Oberschützenmeister);
 *   wer eine hält, bekommt keine automatische Rats-Funktion.
 * - implies_schuetzenrat: wer diese Funktion übernimmt, erhält zusätzlich
 *   automatisch die Rats-Funktion.
 * - is_council: die Rats-Funktion selbst (z. B. Schützenrat, Ausschuss);
 *   höchstens eine Funktion trägt dieses Flag.
 */
class BoardFunction extends Model
{
    public const ACTIVITY_LABELS = [
        'name' => 'Bezeichnung',
        'is_top_vorstand' => 'Spitzenfunktion',
        'implies_schuetzenrat' => 'Erhält automatisch Rats-Funktion',
        'is_council' => 'Rats-Funktion',
        'active' => 'Aktiv',
    ];

    protected $table = 'tb_board_functions';

    protected $primaryKey = 'boardFunctionID';

    protected $guarded = [];

    protected $casts = [
        'is_top_vorstand' => 'boolean',
        'implies_schuetzenrat' => 'boolean',
        'is_council' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * @return array{name: string, is_top_vorstand: bool, implies_schuetzenrat: bool, is_council: bool, active: bool}
     */
    public function activitySnapshot(): array
    {
        return [
            'name' => $this->name,
            'is_top_vorstand' => (bool) $this->is_top_vorstand,
            'implies_schuetzenrat' => (bool) $this->implies_schuetzenrat,
            'is_council' => (bool) $this->is_council,
            'active' => (bool) $this->active,
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            MemberBoardFunctionAssignment::class,
            'boardFunctionID',
            'boardFunctionID'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

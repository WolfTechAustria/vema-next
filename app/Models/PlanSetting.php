<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Im Plattform-Admin angepasstes Paket (Funktionen, Limits).
 */
class PlanSetting extends Model
{
    protected $connection = 'landlord';

    protected $primaryKey = 'plan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }
}

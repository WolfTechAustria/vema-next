<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'tb_settings';

    protected $primaryKey = 'settingsID';

    protected $guarded = [];

    /**
     * Es gibt genau eine Einstellungen-Zeile für den ganzen Verein.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name' => 'VEMA',
        ]);
    }
}

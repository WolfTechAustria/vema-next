<?php

namespace App\Models;

use App\Enums\DemoResetMode;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'tb_settings';

    protected $primaryKey = 'settingsID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'demo_enabled' => 'boolean',
            'demo_reset_mode' => DemoResetMode::class,
            'demo_last_reset_at' => 'datetime',
        ];
    }

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

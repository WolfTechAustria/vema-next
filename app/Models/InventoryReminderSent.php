<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bereits verschickte Material-Erinnerung (Prüfung bzw. Rückgabe je Fälligkeit).
 */
class InventoryReminderSent extends Model
{
    protected $table = 'tb_inventory_reminders_sent';

    protected $guarded = [];

    protected $casts = [
        'due_on' => 'date',
    ];
}

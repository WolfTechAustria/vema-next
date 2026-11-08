<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bezeichnung einer Ehrung (z. B. „Ehrennadel in Gold“) — Vorschlagsliste
 * beim Eintragen, entsteht automatisch.
 */
class Honor extends Model
{
    protected $table = 'tb_honors';

    protected $primaryKey = 'honorID';

    protected $guarded = [];

    /**
     * @return HasMany<MemberHonor, $this>
     */
    public function memberHonors(): HasMany
    {
        return $this->hasMany(MemberHonor::class, 'honorID', 'honorID');
    }
}

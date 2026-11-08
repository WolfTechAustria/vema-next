<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wert eines eigenen Mitgliederfelds; gespeichert als Text
 * (Datum als Y-m-d, Ja/Nein als 1/0).
 */
class MemberFieldValue extends Model
{
    protected $table = 'tb_member_field_values';

    protected $primaryKey = 'valueID';

    protected $guarded = [];

    /**
     * @return BelongsTo<MemberField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(MemberField::class, 'fieldID', 'fieldID');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }
}

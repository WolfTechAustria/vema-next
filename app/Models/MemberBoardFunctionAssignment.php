<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBoardFunctionAssignment extends Model
{
    protected $table = 'tb_member_board_function_assignments';

    protected $primaryKey = 'assignmentID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function boardFunction(): BelongsTo
    {
        return $this->belongsTo(
            BoardFunction::class,
            'boardFunctionID',
            'boardFunctionID'
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('date_to');
    }
}

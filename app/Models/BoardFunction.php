<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardFunction extends Model
{
    protected $table = 'tb_board_functions';

    protected $primaryKey = 'boardFunctionID';

    protected $guarded = [];

    protected $casts = [
        'is_top_vorstand' => 'boolean',
        'implies_schuetzenrat' => 'boolean',
        'active' => 'boolean',
    ];

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

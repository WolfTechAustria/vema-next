<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberPhone extends Model
{
    protected $table = 'tb_phone';

    public $timestamps = false;

    protected $guarded = [];

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function getCategoryNameAttribute(): string
    {
        return match ((int) $this->phoneCategory) {
            1 => 'Privat',
            2 => 'Geschäftlich',
            3 => 'Mutter',
            4 => 'Vater',
            5 => 'Oma',
            6 => 'Opa',
            default => 'Sonstige',
        };
    }
}

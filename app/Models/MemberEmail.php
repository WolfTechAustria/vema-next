<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberEmail extends Model
{
    protected $table = 'tb_email';

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
}

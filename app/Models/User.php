<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'tb_user';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function member()
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }
}

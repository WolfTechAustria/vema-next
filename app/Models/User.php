<?php

namespace App\Models;

use App\Mail\StaffPasswordResetMail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;

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

    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(
            new StaffPasswordResetMail($this, $token)
        );
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

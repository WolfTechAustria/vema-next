<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Betreiber der Plattform (Guard "platform", nur TENANCY_MODE=multi).
 * Liegt fest in der zentralen Datenbank.
 */
class PlatformAdmin extends Authenticatable
{
    protected $connection = 'landlord';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}

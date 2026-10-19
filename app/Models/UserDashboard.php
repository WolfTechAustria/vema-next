<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persönliche Dashboard-Anordnung eines Staff-Benutzers.
 */
class UserDashboard extends Model
{
    protected $table = 'tb_user_dashboards';

    protected $primaryKey = 'userDashboardID';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persönliche Spaltenauswahl der Mitgliederübersicht eines Staff-Benutzers.
 */
class UserMemberColumns extends Model
{
    protected $table = 'tb_user_member_columns';

    protected $primaryKey = 'userMemberColumnsID';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'columns' => 'array',
        ];
    }
}

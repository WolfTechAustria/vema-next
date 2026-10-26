<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresse, die auf der Homepage aufgerufen, aber nicht gefunden wurde —
 * Grundlage für neue Weiterleitungen (siehe HomepageRedirects).
 */
class HomepageNotFound extends Model
{
    protected $table = 'tb_homepage_not_found';

    protected $primaryKey = 'notFoundID';

    protected $guarded = [];

    protected $casts = [
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}

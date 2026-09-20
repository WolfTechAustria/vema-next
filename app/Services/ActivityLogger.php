<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Protokolliert ein grobkörniges Ereignis (Login, Änderung, Neuanlage).
     * Ermittelt den handelnden Akteur selbst — genau ein Guard ist je
     * Request aktiv, die jeweils andere Spalte bleibt leer.
     */
    public static function log(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null
    ): void {
        ActivityLog::create([
            'userID' => Auth::guard('web')->id(),
            'memberAccountID' => Auth::guard('member')->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => request()->ip(),
        ]);
    }
}

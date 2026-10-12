<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CircularRecipient extends Model
{
    protected $table = 'tb_circular_recipients';

    protected $primaryKey = 'recipientID';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function circular(): BelongsTo
    {
        return $this->belongsTo(
            Circular::class,
            'circularID',
            'circularID'
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'memberID',
            'memberID'
        );
    }

    public function externalContact(): BelongsTo
    {
        return $this->belongsTo(
            ExternalContact::class,
            'externalContactID',
            'externalContactID'
        );
    }

    /**
     * Alle E-Mail-Empfänger dieses Rundschreibens mit derselben Adresse
     * (z. B. eine Familie mit gemeinsamer E-Mail). Sie bekommen gemeinsam
     * eine Nachricht, in der jede Person angesprochen wird.
     *
     * @return Collection<int, self>
     */
    public function emailHousehold(): Collection
    {
        if ($this->delivery_method !== 'email' || blank($this->email)) {
            return new Collection([$this]);
        }

        return static::query()
            ->where('circularID', $this->circularID)
            ->where('delivery_method', 'email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [self::normalizeEmail($this->email)])
            ->with(['member.city', 'externalContact'])
            ->orderBy('recipientID')
            ->get();
    }

    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /**
     * Die angeschriebene Person (Mitglied oder externer Kontakt).
     */
    public function addressee(): Member|ExternalContact|null
    {
        return $this->member ?? $this->externalContact;
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->member) {
            return $this->member->full_name;
        }

        if ($this->externalContact) {
            return $this->externalContact->full_name;
        }

        return $this->email ?? 'Unbekannter Empfänger';
    }
}

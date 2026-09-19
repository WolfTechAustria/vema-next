<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MembershipFeePrescription;
use App\Models\CircularRecipient;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BirthdayReminderSent;

class Member extends Model
{
    protected $table = 'tb_members';

    protected $primaryKey = 'memberID';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'competitionMember' => 'boolean',
        'supportingMember' => 'boolean',
        'dateOfBirth' => 'date',
        'dateOfJoin' => 'date',
        'deactiveSince' => 'date',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . $this->surname);
    }

    public function emails()
    {
        return $this->hasMany(
            MemberEmail::class,
            'memberID',
            'memberID'
        );
    }

    public function phones()
    {
        return $this->hasMany(
            MemberPhone::class,
            'memberID',
            'memberID'
        );
    }

    public function city()
    {
        return $this->belongsTo(
            City::class,
            'zip',
            'zip'
        );
    }

    public function dutyVolunteer()
    {
        return $this->hasOne(
            DutyPlanVolunteer::class,
            'memberID',
            'memberID'
        );
    }

    public function dutyAssignments()
    {
        return $this->hasMany(
            DutyPlanAssignment::class,
            'memberID',
            'memberID'
        );
    }

    public function dutyAbsences()
    {
        return $this->hasMany(
            DutyPlanAbsence::class,
            'memberID',
            'memberID'
        );
    }

    public function membershipFeePrescriptions(): HasMany
    {
        return $this->hasMany(
            MembershipFeePrescription::class,
            'memberID',
            'memberID'
        );
    }

    public function circularRecipients(): HasMany
    {
        return $this->hasMany(
            CircularRecipient::class,
            'memberID',
            'memberID'
        );
    }

    public function account()
    {
        return $this->hasOne(
            MemberAccount::class,
            'memberID',
            'memberID'
        );
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(
            MemberAccount::class,
            'tb_member_account_members',
            'memberID',
            'accountID',
            'memberID',
            'accountID'
        )
            ->withPivot('relation')
            ->withTimestamps();
    }

    /**
     * Nur aktive Mitglieder.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Mitglieder, die an einem bestimmten Tag im Jahr (Monat/Tag) Geburtstag haben,
     * unabhängig vom Geburtsjahr.
     */
    public function scopeBirthdayOn(Builder $query, \Carbon\CarbonInterface $date): Builder
    {
        return $query
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $date->month)
            ->whereDay('dateOfBirth', $date->day);
    }

    public function reminderLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            BirthdayReminderSent::class,
            'memberID',
            'memberID'
        );
    }

    /**
     * Aktuelles/erreichtes Alter des Mitglieds (null, falls kein Geburtsdatum hinterlegt ist).
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->dateOfBirth) {
            return null;
        }

        return $this->dateOfBirth->age;
    }

    /**
     * Alter, das das Mitglied an seinem naechsten/aktuellen Geburtstag im
     * uebergebenen Referenzjahr erreicht (bzw. erreicht hat).
     */
    public function ageOn(\Carbon\CarbonInterface $date): ?int
    {
        if (!$this->dateOfBirth) {
            return null;
        }

        return $date->year - $this->dateOfBirth->year;
    }

    /**
     * "Runder" Geburtstag: durch 10 teilbar (30, 40, 50, ...).
     */
    public function isRoundBirthday(int $age): bool
    {
        return $age > 0 && $age % 10 === 0;
    }

    /**
     * "Halbrunder" Geburtstag: durch 5, aber nicht durch 10 teilbar (25, 35, 45, ...).
     */
    public function isHalfRoundBirthday(int $age): bool
    {
        return $age > 0 && $age % 5 === 0 && $age % 10 !== 0;
    }

    public function isRoundOrHalfRoundBirthday(int $age): bool
    {
        return $this->isRoundBirthday($age) || $this->isHalfRoundBirthday($age);
    }




    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(
            Skill::class,
            'tb_skill_members',
            'memberID',
            'skillID',
            'memberID',
            'skillID'
        )->withTimestamps();
    }

    public function recipientGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipientGroup::class,
            'tb_recipient_group_members',
            'memberID',
            'groupID',
            'memberID',
            'groupID'
        )->withTimestamps();
    }

    public function dutySettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            MemberDutySettings::class,
            'memberID',
            'memberID'
        );
    }

    public function dutyReminderEnabled(): bool
    {
        // Standard: aktiviert, solange kein Datensatz existiert.
        return $this->dutySettings?->duty_reminder_enabled ?? true;
    }
}

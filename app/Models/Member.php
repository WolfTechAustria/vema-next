<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        /** @deprecated Wird von keinem App-Code mehr beschrieben, siehe membershipPeriods(). */
        'deactiveSince' => 'date',
    ];

    /*
     * @deprecated tb_members.board_function (rohes int-Feld, kein Cast) wird
     * von keinem App-Code mehr beschrieben, siehe boardFunctionAssignments().
     */

    public function getFullNameAttribute(): string
    {
        return trim($this->name.' '.$this->surname);
    }

    /**
     * Initialen für den Avatar ohne Profilbild, z. B. „AH“.
     */
    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name ?? '', 0, 1).mb_substr($this->surname ?? '', 0, 1));
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
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

    /**
     * Alle Helfer-Zuordnungen dieses Mitglieds über alle Dienstpläne
     * (je Dienstplan eine eigene Zeile, siehe tb_dutyplan_plan_volunteers).
     */
    public function dutyPlanVolunteers(): HasMany
    {
        return $this->hasMany(
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
    public function scopeBirthdayOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $date->month)
            ->whereDay('dateOfBirth', $date->day);
    }

    public function reminderLogs(): HasMany
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
        if (! $this->dateOfBirth) {
            return null;
        }

        return $this->dateOfBirth->age;
    }

    /**
     * Alter, das das Mitglied an seinem naechsten/aktuellen Geburtstag im
     * uebergebenen Referenzjahr erreicht (bzw. erreicht hat).
     */
    public function ageOn(CarbonInterface $date): ?int
    {
        if (! $this->dateOfBirth) {
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

    /**
     * Hochgeladene Dokumente (Beitrittserklärung, Vereinbarungen …).
     */
    public function files(): HasMany
    {
        return $this->hasMany(MemberFile::class, 'memberID', 'memberID');
    }

    /**
     * Externe Kalender, deren Termine das Mitglied in sein iCal-Abo übernimmt.
     */
    public function subscribedEventSources(): BelongsToMany
    {
        return $this->belongsToMany(
            ClubEventSource::class,
            'tb_member_event_source_subscriptions',
            'memberID',
            'sourceID',
            'memberID',
            'sourceID'
        )->withTimestamps();
    }

    public function dutySettings(): HasOne
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

    public function membershipPeriods(): HasMany
    {
        return $this->hasMany(
            MemberMembershipPeriod::class,
            'memberID',
            'memberID'
        );
    }

    public function currentMembershipPeriod(): HasOne
    {
        return $this->hasOne(
            MemberMembershipPeriod::class,
            'memberID',
            'memberID'
        )->whereNull('date_to');
    }

    public function boardFunctionAssignments(): HasMany
    {
        return $this->hasMany(
            MemberBoardFunctionAssignment::class,
            'memberID',
            'memberID'
        );
    }

    public function currentBoardFunctionAssignments(): HasMany
    {
        return $this->hasMany(
            MemberBoardFunctionAssignment::class,
            'memberID',
            'memberID'
        )->whereNull('date_to');
    }

    /**
     * Anzeigenamen der Felder für das Aktivitätsprotokoll.
     *
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'name' => 'Vorname',
        'surname' => 'Nachname',
        'gender' => 'Anrede',
        'dateOfBirth' => 'Geburtsdatum',
        'dateOfJoin' => 'Eintrittsdatum',
        'street' => 'Straße',
        'zip' => 'PLZ',
        'competitionMember' => 'Wettkampfmitglied',
        'supportingMember' => 'Unterstützendes Mitglied',
        'emails' => 'E-Mail-Adressen',
        'phones' => 'Telefonnummern',
        'skills' => 'Fähigkeiten',
    ];

    /**
     * Momentaufnahme der Stammdaten für das Aktivitätsprotokoll
     * (vor und nach einer Änderung, siehe ActivityLogger::changes()).
     *
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        $this->unsetRelation('emails')->unsetRelation('phones')->unsetRelation('skills');

        return [
            'name' => $this->name,
            'surname' => $this->surname,
            'gender' => match ($this->gender) {
                'm' => 'Herr',
                'w' => 'Frau',
                default => $this->gender,
            },
            'dateOfBirth' => $this->dateOfBirth,
            'dateOfJoin' => $this->dateOfJoin,
            'street' => $this->street,
            'zip' => $this->zip,
            'competitionMember' => (bool) $this->competitionMember,
            'supportingMember' => (bool) $this->supportingMember,
            'emails' => $this->emails->pluck('email')->all(),
            'phones' => $this->phones
                ->map(fn ($phone) => $phone->phoneNumber.' ('.$phone->category_name.')')
                ->all(),
            'skills' => $this->skills->pluck('name')->all(),
        ];
    }
}

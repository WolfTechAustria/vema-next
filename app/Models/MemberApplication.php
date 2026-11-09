<?php

namespace App\Models;

use App\Enums\MemberApplicationStatus;
use App\Enums\MembershipKind;
use Carbon\CarbonInterface;
use Database\Factories\MemberApplicationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Online eingereichte Beitrittserklärung. Wird erst nach der Freigabe in
 * der Mitgliederverwaltung zu einem Mitglied.
 */
class MemberApplication extends Model
{
    /** @use HasFactory<MemberApplicationFactory> */
    use HasFactory;

    /**
     * Feldnamen für das Aktivitätsprotokoll (siehe activitySnapshot()).
     *
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'gender' => 'Anrede',
        'name' => 'Vorname',
        'surname' => 'Nachname',
        'dateOfBirth' => 'Geburtsdatum',
        'street' => 'Straße',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'email' => 'E-Mail',
        'phone' => 'Telefon',
        'membership_kind' => 'Mitgliedsart',
        'guardian_name' => 'Erziehungsberechtigte(r)',
    ];

    protected $table = 'tb_member_applications';

    protected $primaryKey = 'applicationID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dateOfBirth' => 'date',
            'applied_on' => 'date',
            'verified_at' => 'datetime',
            'processed_at' => 'datetime',
            'custom_fields' => 'array',
            'consents' => 'array',
            'status' => MemberApplicationStatus::class,
            'membership_kind' => MembershipKind::class,
            'memberID' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->name.' '.$this->surname);
    }

    public function isOpen(): bool
    {
        return $this->status === MemberApplicationStatus::Pending;
    }

    public function isMinorOn(CarbonInterface $date): bool
    {
        return $this->dateOfBirth !== null && $this->dateOfBirth->diffInYears($date) < 18;
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'memberID', 'memberID');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', MemberApplicationStatus::Pending);
    }

    /**
     * Unterschrift als Data-URL (für Anzeige und PDF), sofern vorhanden.
     */
    public function signatureDataUrl(): ?string
    {
        if ($this->signature_path === null || ! Storage::disk('local')->exists($this->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) Storage::disk('local')->get($this->signature_path));
    }

    public function deleteSignature(): void
    {
        if ($this->signature_path !== null) {
            Storage::disk('local')->delete($this->signature_path);
            $this->update(['signature_path' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return [
            'gender' => $this->gender,
            'name' => $this->name,
            'surname' => $this->surname,
            'dateOfBirth' => $this->dateOfBirth?->format('d.m.Y'),
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'email' => $this->email,
            'phone' => $this->phone,
            'membership_kind' => $this->membership_kind?->label(),
            'guardian_name' => $this->guardian_name,
        ];
    }
}

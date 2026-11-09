<?php

namespace App\Models;

use App\Enums\MemberFieldPortal;
use App\Enums\MemberFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eigenes Mitgliederfeld des Vereins (z. B. Instrument, Verbandsnummer).
 */
class MemberField extends Model
{
    /**
     * Feldnamen für das Aktivitätsprotokoll (siehe activitySnapshot()).
     *
     * @var array<string, string>
     */
    public const ACTIVITY_LABELS = [
        'name' => 'Bezeichnung',
        'type' => 'Art',
        'options' => 'Auswahlmöglichkeiten',
        'portal' => 'Mitgliederportal',
        'on_application' => 'In der Online-Anmeldung',
        'active' => 'Aktiv',
    ];

    protected $table = 'tb_member_fields';

    protected $primaryKey = 'fieldID';

    protected $guarded = [];

    protected $casts = [
        'type' => MemberFieldType::class,
        'portal' => MemberFieldPortal::class,
        'options' => 'array',
        'on_application' => 'boolean',
        'active' => 'boolean',
    ];

    /**
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'options' => $this->options ?? [],
            'portal' => $this->portal,
            'on_application' => (bool) $this->on_application,
            'active' => (bool) $this->active,
        ];
    }

    public function placeholder(): string
    {
        return '{{feld_'.$this->key.'}}';
    }

    /**
     * @return HasMany<MemberFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(MemberFieldValue::class, 'fieldID', 'fieldID');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('fieldID');
    }
}

<?php

namespace App\Models;

use App\Enums\DutySwapStatus;
use App\Enums\DutySwapType;
use Database\Factories\DutySwapRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anfrage eines Mitglieds, einen Dienst zu tauschen (gegen einen bestimmten
 * Dienst eines anderen Mitglieds) oder abzugeben (an eine Person oder an
 * alle im Dienstplan).
 */
class DutySwapRequest extends Model
{
    /** @use HasFactory<DutySwapRequestFactory> */
    use HasFactory;

    protected $table = 'tb_duty_swap_requests';

    protected $primaryKey = 'swapID';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => DutySwapType::class,
            'status' => DutySwapStatus::class,
            'responded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requesterID', 'memberID');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DutyPlanAssignment::class, 'assignmentID', 'assignmentID');
    }

    public function targetMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'targetMemberID', 'memberID');
    }

    public function targetAssignment(): BelongsTo
    {
        return $this->belongsTo(DutyPlanAssignment::class, 'targetAssignmentID', 'assignmentID');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'acceptedByID', 'memberID');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidedByUserID', 'id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', DutySwapStatus::open());
    }

    public function isSwap(): bool
    {
        return $this->type === DutySwapType::Swap;
    }

    public function isOpenToAll(): bool
    {
        return $this->type === DutySwapType::Handover && $this->targetMemberID === null;
    }
}

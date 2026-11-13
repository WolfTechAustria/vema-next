<?php

namespace Database\Factories;

use App\Enums\DutySwapStatus;
use App\Enums\DutySwapType;
use App\Models\DutyPlan;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\DutySwapRequest;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutySwapRequest>
 */
class DutySwapRequestFactory extends Factory
{
    /**
     * Offene Abgabe an alle im Dienstplan, samt Dienst des Anfragenden.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => DutySwapType::Handover,
            'requesterID' => fn () => Member::create(['gender' => 'm', 'name' => fake()->firstName(), 'surname' => fake()->lastName(), 'active' => 1])->memberID,
            'assignmentID' => fn (array $attributes) => $this->assignmentFor($attributes['requesterID'])->assignmentID,
            'targetMemberID' => null,
            'status' => DutySwapStatus::Pending,
        ];
    }

    private function assignmentFor(int $memberID): DutyPlanAssignment
    {
        $plan = DutyPlan::create(['name' => 'Plan '.fake()->unique()->word(), 'date_from' => today()->toDateString(), 'date_to' => today()->addYear()->toDateString()]);
        $role = DutyPlanRole::create(['planID' => $plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);
        $event = DutyPlanEvent::create(['planID' => $plan->planID, 'roleID' => $role->roleID, 'duty_date' => today()->addDays(5)->toDateString(), 'duty_name' => 'Bar', 'required_helpers' => 1]);

        return DutyPlanAssignment::create(['eventID' => $event->eventID, 'slot_no' => 1, 'memberID' => $memberID]);
    }
}

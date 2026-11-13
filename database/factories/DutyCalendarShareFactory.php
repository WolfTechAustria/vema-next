<?php

namespace Database\Factories;

use App\Models\DutyCalendarShare;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DutyCalendarShare>
 */
class DutyCalendarShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ownerID' => fn () => Member::create(['gender' => 'm', 'name' => fake()->firstName(), 'surname' => fake()->lastName(), 'active' => 1])->memberID,
            'viewerID' => fn () => Member::create(['gender' => 'w', 'name' => fake()->firstName(), 'surname' => fake()->lastName(), 'active' => 1])->memberID,
            'in_own_calendar' => false,
        ];
    }

    public function inOwnCalendar(): static
    {
        return $this->state(fn (array $attributes) => ['in_own_calendar' => true]);
    }
}

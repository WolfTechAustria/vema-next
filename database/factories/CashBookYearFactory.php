<?php

namespace Database\Factories;

use App\Models\CashBookYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBookYear>
 */
class CashBookYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = today()->subMonths(3)->startOfMonth();
        $end = $start->copy()->addYear()->subDay();

        return [
            'name' => $start->format('Y').'/'.$end->format('y'),
            'start_date' => $start,
            'end_date' => $end,
            'opening_balance' => fake()->randomFloat(2, 0, 2000),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
            'general_meeting_date' => today(),
            'closing_balance' => $attributes['opening_balance'] ?? 0,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\InventoryCondition;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Tracht Herren', 'Tracht Damen', 'Trompete', 'Klarinette', 'Schlüssel Probelokal', 'Festzelt']),
            'inventory_number' => 'M-'.fake()->unique()->numberBetween(1, 99999),
            'location' => fake()->randomElement(['Probelokal', 'Lager', 'Vereinsheim']),
            'condition' => InventoryCondition::Good,
        ];
    }

    public function inspectionDueIn(int $days, int $intervalMonths = 12): static
    {
        return $this->state(fn () => [
            'inspection_interval_months' => $intervalMonths,
            'next_inspection_on' => today()->addDays($days),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['condition' => InventoryCondition::Retired]);
    }
}

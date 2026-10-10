<?php

namespace Database\Factories;

use App\Models\ClubEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubEvent>
 */
class ClubEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 60))->setTime(19, 0);

        return [
            'title' => fake()->randomElement(['Vorstandssitzung', 'Jahreshauptversammlung', 'Sommerfest', 'Ausrückung']),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(3),
            'all_day' => false,
            'rsvp_enabled' => true,
        ];
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = now()->subDays(fake()->numberBetween(2, 60))->setTime(19, 0);

            return [
                'starts_at' => $start,
                'ends_at' => $start->copy()->addHours(3),
            ];
        });
    }

    public function allDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $attributes['starts_at']->copy()->startOfDay(),
            'ends_at' => null,
            'all_day' => true,
        ]);
    }

    public function withoutRsvp(): static
    {
        return $this->state(fn () => [
            'rsvp_enabled' => false,
        ]);
    }
}

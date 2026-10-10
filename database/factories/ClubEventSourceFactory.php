<?php

namespace Database\Factories;

use App\Models\ClubEventSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubEventSource>
 */
class ClubEventSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Verbandskalender', 'Gemeindekalender', 'Bezirksturniere']),
            // Öffentliche IP statt Hostname: Tests kommen ohne DNS-Auflösung aus.
            'url' => 'https://1.1.1.1/calendars/'.fake()->uuid().'.ics',
            'rsvp_enabled' => false,
            'active' => true,
        ];
    }

    public function withRsvp(): static
    {
        return $this->state(fn () => [
            'rsvp_enabled' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}

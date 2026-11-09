<?php

namespace Database\Factories;

use App\Enums\MemberApplicationStatus;
use App\Enums\MembershipKind;
use App\Models\MemberApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberApplication>
 */
class MemberApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gender' => fake()->randomElement(['m', 'w']),
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'dateOfBirth' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'street' => fake()->streetAddress(),
            'zip' => '6300',
            'city' => 'Wörgl',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0664 1234567',
            'membership_type' => 'Ordentliches Mitglied',
            'membership_kind' => MembershipKind::Ordinary,
            'applied_on' => now()->toDateString(),
            'place' => 'Wörgl',
            'consents' => [['text' => 'Ich stimme der Verarbeitung meiner Daten zu.', 'accepted' => true]],
            'status' => MemberApplicationStatus::Pending,
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'status' => MemberApplicationStatus::Unverified,
            'verified_at' => null,
        ]);
    }
}

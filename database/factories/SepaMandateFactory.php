<?php

namespace Database\Factories;

use App\Models\SepaMandate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SepaMandate>
 */
class SepaMandateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'M-'.Str::upper(Str::random(10)),
            'account_holder' => fake()->name(),
            'iban' => 'AT611904300234573201',
            'bic' => 'BKAUATWW',
            'signed_on' => today()->subMonth(),
            'source' => SepaMandate::SOURCE_MANUAL,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['first_used_at' => now()->subYear()]);
    }
}

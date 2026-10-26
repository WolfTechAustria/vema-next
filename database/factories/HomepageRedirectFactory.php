<?php

namespace Database\Factories;

use App\Models\HomepageRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageRedirect>
 */
class HomepageRedirectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_path' => '/alt/'.fake()->unique()->slug(3),
            'target' => '/news',
        ];
    }
}

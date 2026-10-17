<?php

namespace Database\Factories;

use App\Models\HomepagePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HomepagePage>
 */
class HomepagePageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(['Impressum', 'Geschichte', 'Mitglied werden', 'Datenschutz']).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'sort' => 0,
            'in_menu' => true,
            'published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published' => false]);
    }
}

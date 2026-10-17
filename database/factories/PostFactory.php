<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(['Rückblick Sommerfest', 'Neuer Vorstand gewählt', 'Einladung zur Jahreshauptversammlung', 'Erfolg beim Bezirksturnier']).' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => null,
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now()->addDays(3),
        ]);
    }
}

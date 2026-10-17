<?php

namespace Database\Factories;

use App\Enums\HomepageSectionType;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pageID' => HomepagePage::factory(),
            'area' => HomepageSection::AREA_PAGE,
            'type' => HomepageSectionType::Text,
            'sort' => 0,
            'alt_background' => false,
            'heading' => fake()->sentence(3),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'data' => null,
        ];
    }

    public function about(): static
    {
        return $this->state(fn () => [
            'pageID' => null,
            'area' => HomepageSection::AREA_ABOUT,
        ]);
    }
}

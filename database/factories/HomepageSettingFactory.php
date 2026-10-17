<?php

namespace Database\Factories;

use App\Enums\HomepageTemplate;
use App\Models\HomepageSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSetting>
 */
class HomepageSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enabled' => true,
            'template' => HomepageTemplate::Klassisch,
            'primary_color' => '#1d4ed8',
            'accent_color' => '#d7261e',
            'hero_title' => 'Willkommen beim Verein',
            'hero_text' => 'Schön, dass Sie hier sind.',
            'show_board' => true,
            'show_board_photos' => false,
            'contact_visible' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}

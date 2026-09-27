<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->city()).'-'.Str::lower(Str::random(4));

        return [
            'slug' => $slug,
            'name' => 'Verein '.Str::headline($slug),
            'database' => 'vema_tenant_'.Str::replace('-', '_', $slug),
            'status' => TenantStatus::Active,
            'license_valid_until' => now()->addYear(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'plan' => Plan::Starter,
            'email_verified_at' => now(),
            'provisioned_at' => now(),
        ];
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => ['trial_ends_at' => now()->addDays(30)]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TenantStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TenantStatus::Suspended]);
    }

    public function licenseExpired(): static
    {
        return $this->state(fn (array $attributes) => ['license_valid_until' => now()->subDay()]);
    }
}

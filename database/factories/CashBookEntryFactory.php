<?php

namespace Database\Factories;

use App\Enums\CashBookEntryType;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBookEntry>
 */
class CashBookEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cashBookYearID' => CashBookYear::factory(),
            'receipt_number' => fn (array $attributes) => CashBookYear::findOrFail((int) $attributes['cashBookYearID'])->nextReceiptNumber(),
            'date' => fn (array $attributes) => CashBookYear::findOrFail((int) $attributes['cashBookYearID'])->start_date,
            'type' => fake()->randomElement(CashBookEntryType::cases()),
            'amount' => fake()->randomFloat(2, 1, 500),
            'description' => fake()->sentence(3),
            'category' => fake()->randomElement(['Veranstaltung', 'Material', 'Mitgliedsbeiträge', null]),
        ];
    }

    public function income(float $amount = 100): static
    {
        return $this->state(fn () => [
            'type' => CashBookEntryType::Income,
            'amount' => $amount,
        ]);
    }

    public function expense(float $amount = 100): static
    {
        return $this->state(fn () => [
            'type' => CashBookEntryType::Expense,
            'amount' => $amount,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\CashBookAttachment;
use App\Models\CashBookEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBookAttachment>
 */
class CashBookAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cashBookEntryID' => CashBookEntry::factory(),
            'file_name' => 'beleg.jpg',
            'file_path' => 'cash-book/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 12345,
        ];
    }
}

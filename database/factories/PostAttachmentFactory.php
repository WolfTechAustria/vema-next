<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostAttachment>
 */
class PostAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'postID' => Post::factory(),
            'label' => 'Ergebnisliste',
            'file_path' => 'homepage/posts/test/'.Str::random(20).'.pdf',
            'file_size' => 1024,
            'sort' => 0,
        ];
    }
}

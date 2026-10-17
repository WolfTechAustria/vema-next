<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostImage>
 */
class PostImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(20);

        return [
            'postID' => Post::factory(),
            'file_path' => 'homepage/posts/test/'.$name.'.jpg',
            'thumb_path' => 'homepage/posts/test/'.$name.'_thumb.jpg',
            'caption' => null,
            'sort' => 0,
        ];
    }
}

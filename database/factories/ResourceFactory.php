<?php

namespace Database\Factories;

use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<User>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'name' => fake()->sentence(),
            'excerpt' => fake()->sentence(),
            'keywords' => fake()->words(10),
            'description' => fake()->sentence(),
            'ai_summary' => null,
            'published_at' => fake()->dateTime(),
            'language' => 'en',
            // Unique: a resource is one URL
            'url' => fake()->unique()->url(),
            'cover_image_url' => fake()->imageUrl(),
            'cover_image_alt' => fake()->sentence(),
            'cover_image_base64' => base64_encode('image'),
            'type' => KnowiiResourceType::TextArticle,
            'level' => KnowiiResourceLevel::Beginner,
            'is_featured' => false,
            'view_count' => 0,
            'share_count' => 0,
            'last_captured_at' => null,
            'last_checked_at' => null,
            'check_failures_count' => 0,
            'is_unavailable' => false,
        ];
    }
}

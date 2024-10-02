<?php

namespace Database\Factories;

use App\Enums\KnowiiResourceLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;
use App\Enums\KnowiiResourceType;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
      'cuid' => new Cuid2(),
      'name' => fake()->sentence(),
      'excerpt' => fake()->sentence(),
      'description' => fake()->sentence(),
      'ai_summary' => null,
      'published_at' => fake()->dateTime(),
      'language' => 'en',
      'url' => fake()->url(),
      'thumbnail_url' => fake()->url(),
      'type' => KnowiiResourceType::Article,
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

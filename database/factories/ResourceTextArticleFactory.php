<?php

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class ResourceTextArticleFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  final public function definition(): array
  {
    // FIXME test this
    $resource = Resource::factory()->create();

    return [
      'cuid' => new Cuid2(),
      'resource_id' => $resource->id,
      'content' => $this->faker->paragraphs(3, true),
      'word_count' => $this->faker->numberBetween(300, 1000),
      'reading_time' => $this->faker->numberBetween(1, 10),
    ];
  }
}

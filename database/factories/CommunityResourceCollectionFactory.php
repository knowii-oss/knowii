<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Community>
 */
class CommunityResourceCollectionFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  final public function definition(): array
  {
    $community = Community::factory()->create();

    return [
      'cuid' => new Cuid2(),
      'name' => $this->faker->unique()->company(),
      // The slug is generated automatically
      'description' => $this->faker->unique()->sentence(nbWords: 5),
      'community_id' => $community->id,
    ];
  }
}

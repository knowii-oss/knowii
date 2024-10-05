<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityResourceCollection;
use App\Models\Resource;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Community>
 */
class CommunityResourceFactory extends Factory
{
  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  final public function definition(): array
  {
    $community = Community::factory()->create();
    $resourceCollection = CommunityResourceCollection::factory()->create([
      'community_id' => $community->id,
    ]);
    $resource = Resource::factory()->create();

    return [
      'cuid' => new Cuid2(),
      'slug' => $resource->slug,
      'resource_id' => $resource->id,
      'community_id' => $community->id,
      'collection_id' => $resourceCollection->id,
      'curator_id' => UserProfile::factory(),
      'is_featured' => $this->faker->boolean(),
    ];
  }
}

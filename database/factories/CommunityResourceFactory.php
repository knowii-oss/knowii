<?php

namespace Database\Factories;

use App\Enums\KnowiiSpaceType;
use App\Models\Community;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
use App\Models\SpaceGroup;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<CommunityResource>
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
        $space = Space::factory()
            ->inGroup(SpaceGroup::factory()->create(['community_id' => $community->id]))
            ->create(['type' => KnowiiSpaceType::Resources]);
        $resource = Resource::factory()->create();

        return [
            'cuid' => (new Cuid2)->toString(),
            'slug' => $resource->slug,
            'resource_id' => $resource->id,
            'community_id' => $community->id,
            'space_id' => $space->id,
            'curator_id' => UserProfile::factory(),
            'is_featured' => $this->faker->boolean(),
        ];
    }
}

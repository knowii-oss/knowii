<?php

namespace Database\Factories;

use App\Enums\KnowiiCaptureStatus;
use App\Enums\KnowiiResourceLevel;
use App\Enums\KnowiiResourceType;
use App\Enums\KnowiiSpaceType;
use App\Models\CommunityResource;
use App\Models\Resource;
use App\Models\Space;
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
        return [
            'cuid' => (new Cuid2)->toString(),
            'name' => ucfirst($this->faker->words(3, true)),
            'description' => $this->faker->sentence(),
            'resource_id' => Resource::factory(),
            // The slug of a community resource is the slug of its resource
            'slug' => fn (array $attributes) => Resource::query()->whereKey($attributes['resource_id'])->value('slug'),
            'space_id' => Space::factory()->state(['type' => KnowiiSpaceType::Resources]),
            // The resource belongs to the community of its space
            'community_id' => fn (array $attributes) => Space::query()->whereKey($attributes['space_id'])->value('community_id'),
            'curator_id' => UserProfile::factory(),
            'is_featured' => $this->faker->boolean(),
            'type' => KnowiiResourceType::TextArticle,
            'level' => KnowiiResourceLevel::Beginner,
            'capture_status' => KnowiiCaptureStatus::Captured,
        ];
    }
}

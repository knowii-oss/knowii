<?php

namespace Database\Factories;

use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Space;
use App\Models\SpaceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            // The slug is generated automatically
            'description' => $this->faker->sentence(nbWords: 6),
            'emoji' => '💬',
            'type' => KnowiiSpaceType::Discussion,
            'visibility' => KnowiiSpaceVisibility::Members,
            'posting_policy' => KnowiiSpacePostingPolicy::Members,
            'position' => 0,
            'space_group_id' => SpaceGroup::factory(),
            // The space belongs to the community of its group
            'community_id' => fn (array $attributes) => SpaceGroup::query()->whereKey($attributes['space_group_id'])->value('community_id'),
        ];
    }

    /**
     * Put the space in the given group (and its community).
     */
    final public function inGroup(SpaceGroup $spaceGroup): static
    {
        return $this->state(fn () => [
            'space_group_id' => $spaceGroup->id,
            'community_id' => $spaceGroup->community_id,
        ]);
    }
}

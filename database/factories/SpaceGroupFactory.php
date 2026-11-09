<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\SpaceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<SpaceGroup>
 */
class SpaceGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'position' => 0,
            'community_id' => Community::factory(),
        ];
    }
}

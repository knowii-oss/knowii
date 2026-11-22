<?php

namespace Database\Factories;

use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Models\Community;
use App\Models\MemberTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<MemberTag>
 */
class MemberTagFactory extends Factory
{
    /**
     * A gray tag of the managers in a new community. The tests name the community and the name: `for()`, `create()`.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'community_id' => Community::factory(),
            // A short random word: a test that reads the name sets it
            'name' => 'Tag '.$this->faker->unique()->bothify('????##'),
            'color' => KnowiiMemberTagColor::Gray,
            'visibility' => KnowiiMemberTagVisibility::Managers,
        ];
    }

    /**
     * A tag that the members of the community see
     */
    final public function forMembers(): static
    {
        return $this->state(fn () => ['visibility' => KnowiiMemberTagVisibility::Members]);
    }

    /**
     * A tag that only the people who manage the members see
     */
    final public function forManagers(): static
    {
        return $this->state(fn () => ['visibility' => KnowiiMemberTagVisibility::Managers]);
    }
}

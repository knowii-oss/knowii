<?php

namespace Database\Factories;

use App\KnowiiCommunityVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Community>
 */
class CommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $communityName = $this->faker->unique()->company();
        $communitySlug = Str::slug($communityName);

        return [
            'cuid' => new Cuid2(),
            'name' => $communityName,
            'slug' => $communitySlug,
            'description' => $this->faker->unique()->sentence(nbWords: 5),
            'user_id' => User::factory(),
            'visibility' => KnowiiCommunityVisibility::Public,
        ];
    }
}

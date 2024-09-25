<?php

namespace Database\Factories;

use App\Enums\KnowiiCommunityVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
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
        return [
            'cuid' => new Cuid2(),
            'name' => $this->faker->unique()->company(),
            // The slug is generated automatically
            'description' => $this->faker->unique()->sentence(nbWords: 5),
            'visibility' => KnowiiCommunityVisibility::Public,
            'owner_id' => User::factory(),
        ];
    }
}

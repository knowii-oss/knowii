<?php

namespace Database\Factories;

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
        // FIXME might not work
        return [
            'cuid' => new Cuid2(),
            'is_featured' => $this->faker->boolean(),
        ];
    }
}

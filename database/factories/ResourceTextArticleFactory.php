<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<User>
 */
class ResourceTextArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => new Cuid2,
            'resource_id' => Resource::factory(),
            'html' => $this->faker->randomHtml(),
            'word_count' => $this->faker->numberBetween(300, 1000),
            'reading_time' => $this->faker->numberBetween(1, 10),
        ];
    }
}

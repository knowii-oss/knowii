<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use TaylorNetwork\UsernameGenerator\Generator;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<User>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $usernameGenerator = new Generator;

        $name = fake()->name();
        $username = $usernameGenerator->generate($name);

        return [
            'cuid' => new Cuid2,
            'name' => $name,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'profile_photo_path' => null,
            // TODO missing all the other fields
        ];
    }
}

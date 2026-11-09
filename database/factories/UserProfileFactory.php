<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use TaylorNetwork\UsernameGenerator\Generator;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<UserProfile>
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
            'cuid' => (new Cuid2)->toString(),
            // A profile always belongs to an account (#1261, ADR 0024)
            'user_id' => User::factory(),
            'name' => $name,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            // TODO missing all the other fields
        ];
    }
}

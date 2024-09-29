<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TaylorNetwork\UsernameGenerator\Generator;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
    $usernameGenerator = new Generator();

    $name = fake()->name();
    $username = $usernameGenerator->generate($name);

    return [
      'cuid' => new Cuid2(),
      'name' => $name,
      'username' => $username,
      'email' => fake()->unique()->safeEmail(),
      'profile_photo_path' => null,
    ];
  }
}

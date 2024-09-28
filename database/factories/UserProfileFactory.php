<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
    $name = fake()->name();

    return [
      'cuid' => new Cuid2(),
      'name' => $name,
      'profile_photo_path' => null,
    ];
  }
}

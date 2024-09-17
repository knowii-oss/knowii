<?php

namespace Database\Factories;

use App\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Visus\Cuid2\Cuid2;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
  /**
   * The current password being used by the factory.
   */
  protected static ?string $password;

  /**
   * Define the model's default state.
   *
   * @return array<string, mixed>
   */
  final public function definition(): array
  {
    return [
      'name' => fake()->name(),
      'username' => fake()->userName(),
      'email' => fake()->unique()->safeEmail(),
      'email_verified_at' => now(),
      'password' => static::$password ??= Hash::make('password'),
      'two_factor_secret' => null,
      'two_factor_recovery_codes' => null,
      'remember_token' => Str::random(10),
      'profile_photo_path' => null,
      'cuid' => new Cuid2(),
    ];
  }

  /**
   * Indicate that the model's email address should be unverified.
   */
  final public function unverified(): static
  {
    return $this->state(fn(array $attributes) => [
      'email_verified_at' => null,
    ]);
  }

  /**
   * Indicate that the user should have a personal community.
   */
  final public function withPersonalCommunity(?callable $callback = null): static
  {
    return $this->has(
      Community::factory()
        ->state(fn(array $attributes, User $user) => [
          'name' => $user->name . '\'s Space',
          // The slug is generated automatically
          'description' => $user->name . '\'s Personal Space',
          'user_id' => $user->id,
          'visibility' => KnowiiCommunityVisibility::Personal,
        ])
        ->when(is_callable($callback), $callback),
      'ownedCommunities'
    );
  }
}

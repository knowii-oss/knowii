<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<UserIdentity>
 */
class UserIdentityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'user_id' => User::factory(),
            'issuer' => 'https://auth.example.test',
            'subject' => 'sub-'.(new Cuid2)->toString(),
            'linked_at' => now(),
            'last_used_at' => null,
        ];
    }
}

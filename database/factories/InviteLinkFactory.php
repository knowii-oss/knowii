<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\InviteLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<InviteLink>
 */
class InviteLinkFactory extends Factory
{
    /**
     * An active link without an end and without a maximum, in a new community. The tests name the community, the name and
     * the dates: `create([...])`. The token is set with its hash (withToken()).
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $token = InviteLink::newToken();

        return [
            'cuid' => (new Cuid2)->toString(),
            'community_id' => Community::factory(),
            'name' => 'Link '.$this->faker->unique()->bothify('????##'),
            'token' => $token,
            'token_hash' => InviteLink::hashToken($token),
            'active' => true,
            'expires_at' => null,
            'max_uses' => null,
        ];
    }

    /**
     * A link with this token
     */
    final public function withToken(string $token): static
    {
        return $this->state(fn () => ['token' => $token, 'token_hash' => InviteLink::hashToken($token)]);
    }

    /**
     * A link that a manager turned off
     */
    final public function off(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    /**
     * A link whose end is past: one second before the time of the test
     */
    final public function ended(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subSecond()]);
    }

    /**
     * A link through which as many people joined as its maximum
     */
    final public function full(): static
    {
        return $this->state(fn () => ['max_uses' => 1])->afterCreating(static function (InviteLink $link): void {
            $link->forceFill(['uses_count' => 1])->save();
        });
    }
}

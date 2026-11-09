<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $body = $this->faker->sentence();

        return [
            'cuid' => (new Cuid2)->toString(),
            'body' => $body,
            'body_html' => Markdown::render($body),
            'space_id' => Space::factory(),
            'community_id' => fn (array $attributes) => Space::query()->whereKey($attributes['space_id'])->value('community_id'),
            'author_id' => User::factory(),
        ];
    }

    final public function inSpace(Space $space): static
    {
        return $this->state(fn () => ['space_id' => $space->id, 'community_id' => $space->community_id]);
    }

    final public function replyTo(ChatMessage $parent): static
    {
        return $this->state(fn () => ['space_id' => $parent->space_id, 'community_id' => $parent->community_id, 'parent_id' => $parent->id]);
    }
}

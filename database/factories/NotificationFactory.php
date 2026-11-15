<?php

namespace Database\Factories;

use App\Enums\KnowiiNotificationKind;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * A row about a comment (a reply to the recipient). Give it a recipient, a community and a comment with the states.
     *
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        return [
            'cuid' => (new Cuid2)->toString(),
            'user_id' => User::factory(),
            'kind' => KnowiiNotificationKind::CommentReply,
            'comment_id' => Comment::factory(),
            'community_id' => fn (array $attributes) => Comment::query()->whereKey($attributes['comment_id'])->value('community_id'),
            'space_id' => fn (array $attributes) => Comment::query()->whereKey($attributes['comment_id'])->first()?->post?->space_id,
            'actor_id' => User::factory(),
            'dedupe_key' => fn (array $attributes) => 'test:'.(new Cuid2)->toString(),
            'seen_at' => null,
            'read_at' => null,
        ];
    }

    final public function forComment(Comment $comment, User $recipient, ?User $actor = null): static
    {
        return $this->state(fn () => [
            'user_id' => $recipient->id,
            'comment_id' => $comment->id,
            'community_id' => $comment->community_id,
            'space_id' => $comment->post?->space_id,
            'actor_id' => $actor?->id ?? $comment->author_id,
        ]);
    }

    final public function seen(): static
    {
        return $this->state(fn () => ['seen_at' => now()]);
    }

    final public function read(): static
    {
        return $this->state(fn () => ['seen_at' => now(), 'read_at' => now()]);
    }
}

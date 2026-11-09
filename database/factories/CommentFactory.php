<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visus\Cuid2\Cuid2;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    final public function definition(): array
    {
        $body = $this->faker->sentence(8);

        return [
            'cuid' => (new Cuid2)->toString(),
            'body' => $body,
            'body_html' => Markdown::render($body),
            'post_id' => Post::factory(),
            'community_id' => fn (array $attributes) => Post::query()->whereKey($attributes['post_id'])->value('community_id'),
            'author_id' => User::factory(),
            'parent_id' => null,
        ];
    }

    final public function onPost(Post $post): static
    {
        return $this->state(fn () => ['post_id' => $post->id, 'community_id' => $post->community_id]);
    }

    final public function replyTo(Comment $parent): static
    {
        return $this->state(fn () => ['post_id' => $parent->post_id, 'community_id' => $parent->community_id, 'parent_id' => $parent->id]);
    }
}

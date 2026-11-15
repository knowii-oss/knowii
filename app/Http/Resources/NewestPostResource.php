<?php

namespace App\Http\Resources;

use App\Constants;
use App\Models\Post;
use App\Support\Excerpt;
use Illuminate\Http\Request;

/**
 * A post in the list of the newest posts of the public page of a community (#1485): what a visitor may read. The
 * author is the name, the username and the avatar, nothing else. Never the Markdown source, the reactions or the counts.
 *
 * @mixin Post
 */
class NewestPostResource extends AbstractKnowiiJsonResource
{
    /**
     * WARNING: aligned with newestPostSchema (post.schema.ts)
     *
     * @return array<string, mixed>
     */
    final public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $space = $post->space;

        return [
            'cuid' => $post->cuid,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => Excerpt::of($post->body, Constants::PUBLIC_POST_EXCERPT_LENGTH),
            // null when the author deleted their account
            'author' => $post->author?->toResource(AuthorResource::class),
            'space' => ['cuid' => $space->cuid, 'slug' => $space->slug, 'name' => $space->name, 'emoji' => $space->emoji ?? ''],
            'created_at' => $post->created_at,
        ];
    }
}

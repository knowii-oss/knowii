<?php

namespace App\Actions\Posts;

use App\Constants;
use App\Contracts\Posts\CreatesPosts;
use App\Enums\KnowiiSpaceType;
use App\Exceptions\BusinessException;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreatePost implements CreatesPosts
{
    /**
     * Write a post in a discussion space. The space's posting policy decides who can write.
     */
    final public function create(User $user, Space $space, array $input): Post
    {
        Log::info('Processing request to create a post', ['space' => $space->cuid]);

        Gate::forUser($user)->authorize('contribute', $space);

        if ($space->type !== KnowiiSpaceType::Discussion) {
            throw new BusinessException('Posts can only be written in discussion spaces');
        }

        /** @var array{title: string, body: string} $validated */
        $validated = Validator::make($input, self::rules(true))->validate();

        $post = DB::transaction(function () use ($space, $user, $validated) {
            $post = Post::create([
                'community_id' => $space->community_id,
                'space_id' => $space->id,
                'author_id' => $user->id,
                'title' => trim($validated['title']),
                'body' => $validated['body'],
                'body_html' => '',
                'is_pinned' => false,
            ]);
            // Mentions need the post id: render once the post exists
            $post->update(['body_html' => $post->renderBodyAndSyncMentions($validated['body'], $user)]);
            $post->syncUploadUsages($validated['body']);

            return $post;
        });

        Log::info('Post created', ['post' => $post->cuid]);

        return $post;
    }

    /**
     * WARNING: aligned with postFormSchema (post.schema.ts)
     *
     * @return array<string, list<string>>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_POST_TITLE, 'max:'.Constants::MAX_LENGTH_POST_TITLE],
            'body' => [$presence, 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_POST_BODY],
        ];
    }
}

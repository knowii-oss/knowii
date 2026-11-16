<?php

namespace App\Actions\Posts;

use App\Constants;
use App\Contracts\Notifications\FollowsThreads;
use App\Contracts\Notifications\Notifies;
use App\Contracts\Notifications\TracksSpaceVisits;
use App\Contracts\Posts\CreatesPosts;
use App\Enums\KnowiiFollowReason;
use App\Enums\KnowiiSpaceType;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Rules\MaxMarkdownImages;
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

        /** @var array{title: string, body: string, notify_now?: bool} $validated */
        $validated = Validator::make($input, [...self::rules(true), 'notify_now' => ['sometimes', 'boolean']])->validate();

        // "Send now" skips the 5 minutes of the fan-out of a new post: the people who moderate the space only (design 6.11)
        $notifyNow = ($validated['notify_now'] ?? false) === true;
        if ($notifyNow) {
            Gate::forUser($user)->authorize('moderate', $space);
        }

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
        // The space has new content (#1476): the dot of the navigation
        app(TracksSpaceVisits::class)->wroteIn($user, $space);
        // The author follows the thread of their post (#1475)
        app(FollowsThreads::class)->followByWriting($user, $post, KnowiiFollowReason::Wrote);
        // The members mentioned in the post are told (#1474), and after 5 minutes the members at "Everything" (#1475):
        // queued jobs, after the commit
        app(Notifies::class)->notify($post, $notifyNow);
        // Live updates (#1180)
        event(SpaceContentChanged::forPost(SpaceContentChanged::POST_CREATED, $post, $user->cuid));

        return $post;
    }

    /**
     * WARNING: aligned with postFormSchema (post.schema.ts)
     *
     * @return array<string, list<string|object>>
     */
    public static function rules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_POST_TITLE, 'max:'.Constants::MAX_LENGTH_POST_TITLE],
            'body' => [$presence, 'string', 'min:1', 'max:'.Constants::MAX_LENGTH_POST_BODY, new MaxMarkdownImages],
        ];
    }
}

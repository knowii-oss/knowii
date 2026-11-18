<?php

namespace App\Actions\Users;

use App\Contracts\Content\LeavesMarkers;
use App\Contracts\Notifications\PrunesNotifications;
use App\Contracts\Users\DeletesMemberContent;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Mention;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\UploadUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes what a member wrote in every community, when they delete their account and choose to (#1344, ADR 0043).
 *
 * - A post that still has comments of other members stays as a marker (no title, no text, no author). Any other post
 *   goes, with its own comments.
 * - A comment that still has replies, and a chat message that still has replies in its thread, stay as markers, like
 *   the chat messages that members delete (#1185). Any other one goes.
 * - A direct message of the member stays as a marker for the other participants, who keep their own messages.
 * - Resources, pages and lessons are not touched: they stay under "Deleted member" (ADR 0024).
 *
 * The work runs in chunks of ids, in the transaction of the caller (or its own). A marker loses its author, so the next
 * chunk never meets it again. The markers of posts and comments come from `LeavesMarkers`, the same as an ordinary
 * delete (#1565, ADR 0050). No event is sent per item: the open pages show the change when they load again.
 */
final class DeleteMemberContent implements DeletesMemberContent
{
    private const int CHUNK = 500;

    final public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->deletePosts($user);
            $this->deleteComments($user);
            $this->deleteChatMessages($user);
            $this->deleteDirectMessages($user);
        });

        Log::info('Content of the user deleted', ['user' => $user->cuid]);
    }

    private function deletePosts(User $user): void
    {
        $markers = app(LeavesMarkers::class);
        while (($ids = self::nextIds(Post::query()->where('author_id', $user->id))) !== []) {
            // Posts with comments of other members (a deleted account counts, the markers do not)
            $kept = $markers->postsAnsweredByOthers($ids, $user);
            $removed = array_values(array_diff($ids, $kept));

            if ($removed !== []) {
                $prunes = app(PrunesNotifications::class);
                $prunes->forPosts($removed);
                // The cascades remove the comments of the member, with their reactions, mentions and usages
                $prunes->forComments(self::ids(Comment::query()->whereIn('post_id', $removed)));
                Post::query()->whereIn('id', $removed)->delete();
            }

            // The same marker as an ordinary delete (#1565)
            $markers->markPosts($kept);
        }
    }

    private function deleteComments(User $user): void
    {
        // The replies first: a comment keeps a marker only for the replies of others
        while (($ids = self::nextIds(Comment::query()->where('author_id', $user->id)->whereNotNull('parent_id'))) !== []) {
            $parents = Comment::query()->whereIn('id', $ids)->select('parent_id')->distinct()->get()
                ->map(static fn (Comment $comment): int => (int) $comment->parent_id)->all();

            app(PrunesNotifications::class)->forComments($ids);
            Comment::query()->whereIn('id', $ids)->delete();

            // A marker whose last reply went has nothing left to hold
            $orphans = self::ids(Comment::query()->whereIn('id', $parents)->whereNotNull('deleted_at')->whereDoesntHave('replies'));
            if ($orphans !== []) {
                Comment::query()->whereIn('id', $orphans)->delete();
            }
        }

        $markers = app(LeavesMarkers::class);
        while (($ids = self::nextIds(Comment::query()->where('author_id', $user->id))) !== []) {
            // The own replies are gone: any reply left is of another member
            $kept = $markers->commentsAnsweredByOthers($ids, $user);
            $removed = array_values(array_diff($ids, $kept));

            if ($removed !== []) {
                app(PrunesNotifications::class)->forComments($removed);
                Comment::query()->whereIn('id', $removed)->delete();
            }

            // The same marker as an ordinary delete (#1565)
            $markers->markComments($kept);
        }
    }

    private function deleteChatMessages(User $user): void
    {
        // The replies of threads first: a message keeps a marker only for the replies of others
        while (($ids = self::nextIds(self::spaceMessagesOf($user)->whereNotNull('parent_id'))) !== []) {
            $parents = array_values(ChatMessage::query()->whereIn('id', $ids)->select('parent_id')->distinct()->get()
                ->map(static fn (ChatMessage $message): int => (int) $message->parent_id)->all());

            app(PrunesNotifications::class)->forChatMessages($ids);
            ChatMessage::query()->whereIn('id', $ids)->delete();

            self::countReplies($parents);
            // A marker whose last reply went has nothing left to hold
            $orphans = self::ids(ChatMessage::query()->whereIn('id', $parents)->whereNotNull('deleted_at')->where('reply_count', 0));
            if ($orphans !== []) {
                ChatMessage::query()->whereIn('id', $orphans)->delete();
            }
        }

        while (($ids = self::nextIds(self::spaceMessagesOf($user))) !== []) {
            $withReplies = ChatMessage::query()->whereIn('parent_id', $ids)->whereNull('deleted_at')->select('parent_id')->distinct()->get()
                ->map(static fn (ChatMessage $message): int => (int) $message->parent_id)->all();
            $removed = array_values(array_diff($ids, $withReplies));

            app(PrunesNotifications::class)->forChatMessages($ids);
            if ($removed !== []) {
                ChatMessage::query()->whereIn('id', $removed)->delete();
            }

            $kept = array_values($withReplies);
            if ($kept !== []) {
                ChatMessage::query()->whereIn('id', $kept)->update(['body' => '', 'body_html' => '', 'author_id' => null, 'deleted_at' => now()]);
                self::clearMarkers($kept);
            }
        }
    }

    private function deleteDirectMessages(User $user): void
    {
        // The other participants keep their messages and see a marker in place of these (no threads in conversations)
        while (($ids = self::nextIds(ChatMessage::query()->where('author_id', $user->id)->whereNotNull('conversation_id'))) !== []) {
            app(PrunesNotifications::class)->forChatMessages($ids);
            ChatMessage::query()->whereIn('id', $ids)->update(['body' => '', 'body_html' => '', 'author_id' => null, 'deleted_at' => now()]);
            self::clearMarkers($ids);
        }
    }

    /**
     * The messages of the member in chat spaces
     *
     * @return Builder<ChatMessage>
     */
    private static function spaceMessagesOf(User $user): Builder
    {
        return ChatMessage::query()->where('author_id', $user->id)->whereNotNull('space_id');
    }

    /**
     * The reactions, the mentions and the upload usages of chat messages that became markers (the cascades only cover
     * the rows that go). Posts and comments: `LeavesMarkers`.
     *
     * @param  list<int>  $ids
     */
    private static function clearMarkers(array $ids): void
    {
        Reaction::query()->whereIn('chat_message_id', $ids)->delete();
        Mention::query()->whereIn('chat_message_id', $ids)->delete();
        UploadUsage::query()->whereIn('chat_message_id', $ids)->delete();
    }

    /**
     * The reply count and the last reply date of threads, as DeleteChatMessage computes them: replies that are not
     * deleted.
     *
     * @param  list<int>  $parentIds
     */
    private static function countReplies(array $parentIds): void
    {
        if ($parentIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        DB::update(
            'UPDATE chat_messages SET reply_count = (SELECT COUNT(*) FROM chat_messages r WHERE r.parent_id = chat_messages.id AND r.deleted_at IS NULL), '
            .'last_reply_at = (SELECT MAX(r.created_at) FROM chat_messages r WHERE r.parent_id = chat_messages.id AND r.deleted_at IS NULL) '
            ."WHERE id IN ($placeholders)",
            $parentIds,
        );
    }

    /**
     * The next chunk of ids of a query whose rows leave it when they are handled.
     *
     * @param  Builder<Post>|Builder<Comment>|Builder<ChatMessage>  $query
     * @return list<int>
     */
    private static function nextIds(Builder $query): array
    {
        return self::ids($query->orderBy('id')->limit(self::CHUNK));
    }

    /**
     * @param  Builder<Post>|Builder<Comment>|Builder<ChatMessage>  $query
     * @return list<int>
     */
    private static function ids(Builder $query): array
    {
        return array_values($query->select('id')->get()->map(static fn (Post|Comment|ChatMessage $item): int => $item->id)->all());
    }
}

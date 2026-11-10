<?php

namespace App\Support;

use App\Models\Community;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The conversations of a member in a community, newest first, with their unread counts (#1186)
 */
final class Conversations
{
    /**
     * @return BelongsToMany<Conversation, covariant User>
     */
    public static function of(User $user, Community $community): BelongsToMany
    {
        return $user->conversations()
            ->where('conversations.community_id', $community->id)
            ->select('conversations.*')
            ->selectSub(self::unreadSubquery($user), 'unread_count')
            ->with(['participants', 'latestMessage.author'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id');
    }

    /**
     * The messages that the member did not read in all their conversations of the community
     */
    public static function unreadTotal(User $user, Community $community): int
    {
        return (int) DB::table('chat_messages')
            ->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'chat_messages.conversation_id')
            ->where('chat_messages.community_id', $community->id)
            ->where('conversation_participants.user_id', $user->id)
            ->whereColumn('chat_messages.id', '>', 'conversation_participants.last_read_message_id')
            ->where(fn (QueryBuilder $query) => $query->whereNull('chat_messages.author_id')->orWhere('chat_messages.author_id', '!=', $user->id))
            ->whereNull('chat_messages.deleted_at')
            ->count();
    }

    /**
     * For one conversation of the list: the messages after the read marker of the member, by the others
     */
    private static function unreadSubquery(User $user): QueryBuilder
    {
        return DB::table('chat_messages')
            ->selectRaw('count(*)')
            ->whereColumn('chat_messages.conversation_id', 'conversations.id')
            ->whereColumn('chat_messages.id', '>', 'conversation_participants.last_read_message_id')
            ->where(fn (QueryBuilder $query) => $query->whereNull('chat_messages.author_id')->orWhere('chat_messages.author_id', '!=', $user->id))
            ->whereNull('chat_messages.deleted_at');
    }
}

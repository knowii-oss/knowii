<?php

namespace App\Actions\Conversations;

use App\Actions\Chat\CreateChatMessage;
use App\Contracts\Conversations\StartsConversations;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\Conversation;
use App\Models\User;
use App\Support\ChatMessageEvents;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Start a conversation of direct messages with 1 to 9 other members of the community, with a first message (#1186,
 * ADR 0028). Two members share one one-to-one conversation: writing again to the same member continues it. A group is
 * a new conversation each time. Nobody adds a member they blocked or who blocked them.
 */
class StartConversation implements StartsConversations
{
    final public function start(User $user, Community $community, array $input): Conversation
    {
        if (! $user->belongsToCommunity($community)) {
            throw new AuthorizationException('Only the members of the community write direct messages');
        }
        if (! $community->allowsDirectMessages()) {
            throw new BusinessException('Direct messages are turned off in this community');
        }

        /** @var array{participant_cuids: list<string>, body: string} $validated */
        $validated = Validator::make($input, [
            'participant_cuids' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_PARTICIPANTS - 1)],
            'participant_cuids.*' => ['required', 'string', 'distinct'],
            ...CreateChatMessage::rules(),
        ])->validate();

        $others = self::members($user, $community, $validated['participant_cuids']);

        foreach ($others as $other) {
            if ($user->hasBlockWith($other)) {
                throw new BusinessException('You cannot write to one of these members');
            }
        }

        $direct = count($others) === 1 ? $others[0] : null;

        [$conversation, $message] = DB::transaction(function () use ($user, $community, $others, $direct, $validated) {
            $conversation = $direct !== null
                ? $community->conversations()->where('direct_key', Conversation::directKey($user, $direct))->first()
                : null;

            if ($conversation === null) {
                $conversation = Conversation::create([
                    'community_id' => $community->id,
                    'created_by' => $user->id,
                    'direct_key' => $direct !== null ? Conversation::directKey($user, $direct) : null,
                ]);
                $conversation->participants()->attach([$user->id, ...array_map(static fn (User $other) => $other->id, $others)]);
            }

            return [$conversation, SendDirectMessage::write($user, $conversation, $validated['body'])];
        });

        Log::info('Conversation started', ['conversation' => $conversation->cuid, 'participants' => count($others) + 1]);
        ChatMessageEvents::dispatch(ChatMessageEvents::CREATED, $message, $user->cuid);

        return $conversation;
    }

    /**
     * The other participants: members of the community, the author left out
     *
     * @param  list<string>  $cuids
     * @return list<User>
     */
    private static function members(User $user, Community $community, array $cuids): array
    {
        $cuids = array_values(array_diff($cuids, [$user->cuid]));

        $members = User::query()
            ->whereIn('cuid', $cuids)
            ->where(fn ($query) => $query
                ->where('id', $community->owner_id)
                ->orWhereHas('communities', fn ($communities) => $communities->whereKey($community->id)))
            ->get();

        if ($cuids === [] || $members->count() !== count($cuids)) {
            throw ValidationException::withMessages(['participant_cuids' => 'Choose other members of this community']);
        }

        /** @var list<User> $list */
        $list = array_values($members->all());

        return $list;
    }
}

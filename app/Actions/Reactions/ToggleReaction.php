<?php

namespace App\Actions\Reactions;

use App\Contracts\Reactions\TogglesReactions;
use App\Events\Spaces\SpaceContentChanged;
use App\Exceptions\BusinessException;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\ChatMessageEvents;
use App\Support\Emoji;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ToggleReaction implements TogglesReactions
{
    /**
     * Add the reaction of the member, or remove it when it exists (one of each emoji per member and item, #1204).
     * An item takes at most Emoji::MAX_PER_ITEM different emoji. Returns the new summary of the item.
     *
     * @throws BusinessException
     */
    final public function toggle(User $user, Post|Comment|ChatMessage $reactable, array $input): array
    {
        Gate::forUser($user)->authorize('react', $reactable);

        /** @var array{emoji: string} $validated */
        $validated = Validator::make($input, [
            // An emoji of the allow-list (resources/emoji/allowed.json), never free text
            'emoji' => ['required', 'string', 'max:64', static function (string $attribute, mixed $value, Closure $fail) {
                if (! Emoji::isAllowed($value)) {
                    $fail('Choose an emoji from the picker.');
                }
            }],
        ])->validate();
        $emoji = $validated['emoji'];

        $existing = $reactable->reactions()->where('user_id', $user->id)->where('emoji', $emoji)->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $isNewEmoji = ! $reactable->reactions()->where('emoji', $emoji)->exists();
            if ($isNewEmoji && $reactable->reactions()->distinct()->count('emoji') >= Emoji::MAX_PER_ITEM) {
                throw new BusinessException('This item already has '.Emoji::MAX_PER_ITEM.' different reactions. Add yours to one of them.');
            }
            // Two quick clicks must not fail on the unique index
            $reactable->reactions()->firstOrCreate(
                ['user_id' => $user->id, 'emoji' => $emoji],
                ['community_id' => $reactable->community_id],
            );
        }

        if ($reactable instanceof ChatMessage) {
            ChatMessageEvents::dispatch(ChatMessageEvents::REACTIONS, $reactable);
        } else {
            event(SpaceContentChanged::forReactions($reactable));
        }

        return $reactable->load('reactions')->reactionSummary($user);
    }
}

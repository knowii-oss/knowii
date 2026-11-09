<?php

namespace App\Actions\Reactions;

use App\Contracts\Reactions\TogglesReactions;
use App\Enums\KnowiiReactionKind;
use App\Events\Spaces\SpaceContentChanged;
use App\Models\ChatMessage;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ToggleReaction implements TogglesReactions
{
    /**
     * Add the reaction of the member, or remove it when it exists (one of each kind per member and item).
     * Returns the new summary of the item.
     */
    final public function toggle(User $user, Post|Comment|ChatMessage $reactable, array $input): array
    {
        Gate::forUser($user)->authorize('react', $reactable);

        /** @var array{kind: string} $validated */
        $validated = Validator::make($input, [
            // WARNING: aligned with REACTION_KINDS (reaction.schema.ts)
            'kind' => ['required', Rule::enum(KnowiiReactionKind::class)],
        ])->validate();

        $existing = $reactable->reactions()->where('user_id', $user->id)->where('kind', $validated['kind'])->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            // Two quick clicks must not fail on the unique index
            $reactable->reactions()->firstOrCreate(
                ['user_id' => $user->id, 'kind' => $validated['kind']],
                ['community_id' => $reactable->community_id],
            );
        }

        event(SpaceContentChanged::forReactions($reactable));

        return $reactable->load('reactions')->reactionSummary($user);
    }
}

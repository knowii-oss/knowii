<?php

namespace App\Traits;

use App\Enums\KnowiiReactionKind;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Items that members react to (posts, comments).
 */
trait HasReactions
{
    /**
     * The column of the reactions table that points to this kind of item (post_id, comment_id).
     */
    abstract protected function reactionForeignKey(): string;

    /**
     * @return HasMany<Reaction, covariant $this>
     */
    final public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, $this->reactionForeignKey());
    }

    /**
     * Count per kind and whether the user reacted, in the fixed order of the kinds; kinds without a reaction are left out.
     * Eager load `reactions` on lists to avoid a query per item.
     *
     * @return list<array{kind: string, count: int, reacted: bool}>
     */
    final public function reactionSummary(?User $user): array
    {
        $summary = [];

        foreach (KnowiiReactionKind::cases() as $kind) {
            $ofKind = $this->reactions->filter(fn (Reaction $reaction) => $reaction->kind === $kind);
            if ($ofKind->isEmpty()) {
                continue;
            }
            $summary[] = [
                'kind' => $kind->value,
                'count' => $ofKind->count(),
                'reacted' => $user !== null && $ofKind->contains(fn (Reaction $reaction) => $reaction->user_id === $user->id),
            ];
        }

        return $summary;
    }
}

<?php

namespace App\Traits;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Items that members react to (posts, comments).
 */
trait HasReactions
{
    /**
     * The column of the reactions and mentions tables that points to this kind of item (post_id, comment_id).
     */
    abstract protected function itemForeignKey(): string;

    /**
     * @return HasMany<Reaction, covariant $this>
     */
    final public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, $this->itemForeignKey());
    }

    /**
     * Count per emoji and whether the user reacted (#1204), in the order of the first reaction with each emoji, so a new
     * emoji comes last. Eager load `reactions` on lists to avoid a query per item.
     *
     * @return list<array{emoji: string, count: int, reacted: bool}>
     */
    final public function reactionSummary(?User $user): array
    {
        return array_values($this->reactions
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->groupBy('emoji')
            ->map(fn ($ofEmoji, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $ofEmoji->count(),
                'reacted' => $user !== null && $ofEmoji->contains(fn (Reaction $reaction) => $reaction->user_id === $user->id),
            ])
            ->all());
    }
}

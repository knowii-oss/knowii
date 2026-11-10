<?php

namespace App\Traits;

use App\Models\Mention;
use App\Models\User;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Items whose Markdown can mention members (posts, comments). Uses the same item column as the reactions (ADR 0014).
 */
trait HasMentions
{
    /**
     * @return HasMany<Mention, covariant $this>
     */
    final public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class, $this->itemForeignKey());
    }

    /**
     * Render the body with the mentions of the community members, and keep the mentions in step.
     * The author is never stored as mentioned (no notification to oneself).
     *
     * @return string The HTML of the body
     */
    final public function renderBodyAndSyncMentions(string $body, ?User $author): string
    {
        $rendered = Markdown::renderWithMentions($body, $this->community);

        $this->mentions()->delete();
        foreach ($rendered->mentioned as $member) {
            if (($author !== null && $member->id === $author->id) || ! $this->canMention($member)) {
                continue;
            }
            $this->mentions()->create(['community_id' => $this->community_id, 'user_id' => $member->id]);
        }

        return $rendered->html;
    }

    /**
     * Whether a member can be mentioned in this item: every member of the community, unless the item says otherwise
     * (a direct message mentions its participants only, #1186)
     */
    protected function canMention(User $member): bool
    {
        return true;
    }
}

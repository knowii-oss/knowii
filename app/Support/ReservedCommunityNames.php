<?php

namespace App\Support;

use App\Models\Community;
use App\Models\User;
use Cviebrock\EloquentSluggable\Services\SlugService;

/**
 * The names that only the administrators of the instance give to a shared community (#1340, ADR 0036): the built-in
 * list and the names of the instance (`config/communities.php`). A name is reserved when it equals a reserved name,
 * whatever the case, or gives the same address (slug), like the unique names of ADR 0033.
 */
final class ReservedCommunityNames
{
    public static function isReserved(string $name): bool
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return false;
        }
        $slug = self::slug($name);

        foreach (self::names() as $reserved) {
            if (mb_strtolower($reserved) === $name || self::slug($reserved) === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user can give a reserved name to a community: an account of the instance's list, with a verified
     * email address (an unverified account could claim any address).
     */
    public static function canBeUsedBy(?User $user): bool
    {
        if ($user === null || ! $user->hasVerifiedEmail()) {
            return false;
        }

        return in_array(mb_strtolower($user->email), self::owners(), true);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [...self::strings(config('communities.reserved_names')), ...self::strings(config('communities.extra_reserved_names'))];
    }

    /**
     * @return list<string>
     */
    private static function owners(): array
    {
        return array_map(mb_strtolower(...), self::strings(config('communities.reserved_names_owners')));
    }

    private static function slug(string $name): string
    {
        return SlugService::createSlug(Community::class, 'slug', $name, ['unique' => false]);
    }

    /**
     * The strings of a configuration value: anything else is ignored.
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
    }
}

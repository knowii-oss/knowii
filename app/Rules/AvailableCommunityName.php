<?php

namespace App\Rules;

use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use App\Support\ReservedCommunityNames;
use Closure;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The name of a shared (private or public) community is unique (ADR 0033, #1136). A name is taken when another
 * shared community has the same name, whatever the case, or has the address (slug) that this name gives.
 * Personal communities never take a name: each member has one, and nobody else sees it.
 *
 * A reserved name (ADR 0036, #1340) is taken for everyone except the administrators of the instance. The answer is
 * the same as for a name that a community has: nobody learns which names are reserved.
 */
final readonly class AvailableCommunityName implements ValidationRule
{
    public const string MESSAGE = 'The name is already taken.';

    /**
     * @param  Community|null  $except  the community that is renamed: its own name and address stay available to it
     * @param  User|null  $user  the user who creates or renames the community: reserved names are for some users only
     */
    public function __construct(private ?Community $except = null, private ?User $user = null) {}

    public static function isAvailable(string $name, ?Community $except = null, ?User $user = null): bool
    {
        $name = trim($name);
        $slug = SlugService::createSlug(Community::class, 'slug', $name, ['unique' => false]);

        // A community keeps its own name and address, also when the name became reserved after its creation
        $isOwnName = $except !== null && (mb_strtolower($except->name) === mb_strtolower($name) || $except->slug === $slug);
        if (! $isOwnName && ReservedCommunityNames::isReserved($name) && ! ReservedCommunityNames::canBeUsedBy($user)) {
            return false;
        }

        return ! Community::query()
            ->where('visibility', '!=', KnowiiCommunityVisibility::Personal)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->where(static fn ($query) => $query->whereRaw('lower(name) = lower(?)', [$name])->orWhere('slug', $slug))
            ->exists();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! self::isAvailable($value, $this->except, $this->user)) {
            $fail(self::MESSAGE);
        }
    }
}

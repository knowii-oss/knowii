<?php

namespace App\Rules;

use App\Enums\KnowiiCommunityVisibility;
use App\Models\Community;
use Closure;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The name of a shared (private or public) community is unique (ADR 0033, #1136). A name is taken when another
 * shared community has the same name, whatever the case, or has the address (slug) that this name gives.
 * Personal communities never take a name: each member has one, and nobody else sees it.
 */
final readonly class AvailableCommunityName implements ValidationRule
{
    public const string MESSAGE = 'The name is already taken.';

    /**
     * @param  Community|null  $except  the community that is renamed: its own name and address stay available to it
     */
    public function __construct(private ?Community $except = null) {}

    public static function isAvailable(string $name, ?Community $except = null): bool
    {
        $name = trim($name);
        $slug = SlugService::createSlug(Community::class, 'slug', $name, ['unique' => false]);

        return ! Community::query()
            ->where('visibility', '!=', KnowiiCommunityVisibility::Personal)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->where(static fn ($query) => $query->whereRaw('lower(name) = lower(?)', [$name])->orWhere('slug', $slug))
            ->exists();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! self::isAvailable($value, $this->except)) {
            $fail(self::MESSAGE);
        }
    }
}

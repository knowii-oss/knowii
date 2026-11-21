<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An address that no other account has, without regard to the case (#1706, ADR 0058): the sign-up, the profile form and the
 * provider find an account by its address that way, and the unique index on lower(email) refuses a second one. A pending
 * address of another account does not count. The message is the one of the rule "unique" of the framework.
 */
final readonly class FreeEmailAddress implements ValidationRule
{
    /**
     * @param  ?int  $ignoredUserId  The account whose own address is not "another account" (the profile form of the account)
     */
    public function __construct(private ?int $ignoredUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isTaken($value, $this->ignoredUserId)) {
            $fail(__('validation.unique', ['attribute' => $attribute]));
        }
    }

    /**
     * Whether an account other than the given one has the address, without regard to the case
     */
    public static function isTaken(string $address, ?int $ignoredUserId = null): bool
    {
        return User::query()
            ->when($ignoredUserId !== null, static fn ($query) => $query->whereKeyNot($ignoredUserId))
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($address))])
            ->exists();
    }

    /**
     * The validation error of an address that another account has: for a write that the unique index refused
     *
     * @return array<string, list<string>>
     */
    public static function takenMessages(string $attribute = 'email'): array
    {
        return [$attribute => [__('validation.unique', ['attribute' => $attribute])]];
    }
}

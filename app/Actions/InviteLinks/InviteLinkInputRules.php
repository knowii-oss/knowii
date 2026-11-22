<?php

namespace App\Actions\InviteLinks;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\MemberTag;
use App\Models\User;
use App\Rules\WholeNumber;
use App\Support\InvitationGrants;
use App\Support\InviteLinkTexts;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The fields of an invite link (#1391, ADR 0062). WARNING: aligned with inviteLinkFormSchema (invite-link.schema.ts)
 *
 * The groups and the tags go through InvitationGrants (#1390): the same lists, limits and rights as an invitation. A
 * group that a rule on an entitlement feeds is refused: a link would be a free pass to it.
 */
final class InviteLinkInputRules
{
    /**
     * An ISO 8601 time with its zone, as `Date.prototype.toISOString()` gives it
     */
    private const string ISO_TIME_PATTERN = '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})\z/';

    /**
     * The name as it is stored: trimmed, and each run of spaces inside it made one space (as a member tag)
     *
     * @param  array<mixed>  $input
     */
    public static function cleanName(array &$input): void
    {
        if (is_string($input['name'] ?? null)) {
            $input['name'] = MemberTag::cleanName($input['name']);
        }
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function editable(bool $required): array
    {
        return [
            'name' => [
                $required ? 'required' : 'sometimes', 'string',
                'min:'.Constants::MIN_LENGTH_INVITE_LINK_NAME, 'max:'.Constants::MAX_LENGTH_INVITE_LINK_NAME,
                // One line, without an invisible format character, as the name of a tag
                'not_regex:/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u',
            ],
            'active' => ['sometimes', 'boolean:strict'],
            'expires_at' => ['sometimes', 'nullable', 'string', 'regex:'.self::ISO_TIME_PATTERN],
            // A number or a text of digits: `integer` alone takes `true` as 1
            'max_uses' => ['sometimes', 'nullable', 'bail', new WholeNumber, 'integer', 'min:1', 'max:'.Constants::MAX_INVITE_LINK_USES],
        ];
    }

    /**
     * The end of the request, later than now, or null when the request clears it. Called only when the field is there
     *
     * @throws ValidationException
     */
    public static function expiresAt(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            $time = CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['expires_at' => 'The end must be a time']);
        }
        if (! $time->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => InviteLinkTexts::END_IN_PAST]);
        }

        return $time;
    }

    /**
     * A personal community has no invite link: a validation error on `community`, as the setting "Who can join"
     *
     * @throws ValidationException
     */
    public static function ensureShared(Community $community): void
    {
        if ($community->visibility === KnowiiCommunityVisibility::Personal) {
            throw ValidationException::withMessages(['community' => InviteLinkTexts::PERSONAL_COMMUNITY]);
        }
    }

    /**
     * The groups and the tags of the request, read by InvitationGrants with its rights, and no group with a rule on an
     * entitlement. The check reads the stored rules, whether the feature of ADR 0047 is on or not.
     *
     * @param  array<mixed>  $input
     * @return array{access_groups: Collection<int, AccessGroup>, member_tags: Collection<int, MemberTag>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function grants(User $user, Community $community, array $input): array
    {
        $grants = InvitationGrants::read($user, $community, $input, InvitationGrants::HOLDER_INVITE_LINK);

        $groups = $grants[InvitationGrants::FIELD_ACCESS_GROUPS];
        if ($groups->isNotEmpty() && AccessGroup::query()->whereKey($groups->modelKeys())->whereHas('rules')->exists()) {
            throw ValidationException::withMessages([InvitationGrants::FIELD_ACCESS_GROUPS => InviteLinkTexts::FED_BY_RULE]);
        }

        return $grants;
    }

    /**
     * The fields of the request without the groups and the tags
     *
     * @param  array<mixed>  $input
     * @return array{name?: string, active?: bool, expires_at?: string|null, max_uses?: int|string|null}
     *
     * @throws ValidationException
     */
    public static function validate(array $input, bool $required): array
    {
        self::cleanName($input);

        /** @var array{name?: string, active?: bool, expires_at?: string|null, max_uses?: int|string|null} $validated */
        $validated = Validator::make($input, self::editable($required), [
            'expires_at.regex' => 'The end must be an ISO 8601 time with its zone',
        ])->validate();

        return $validated;
    }
}

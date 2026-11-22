<?php

namespace App\Actions\MemberTags;

use App\Constants;
use App\Enums\KnowiiMemberTagColor;
use App\Enums\KnowiiMemberTagVisibility;
use App\Models\Community;
use App\Models\MemberTag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The fields of a member tag (#1389, ADR 0060). WARNING: aligned with memberTagFormSchema (member-tag.schema.ts)
 */
final class MemberTagInputRules
{
    public const string NAME_TAKEN = 'A tag with this name exists already.';

    /**
     * Clean the name of the input (MemberTag::cleanName), before the validation
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
                'min:'.Constants::MIN_LENGTH_MEMBER_TAG_NAME, 'max:'.Constants::MAX_LENGTH_MEMBER_TAG_NAME,
                // No control character, so no line break and no tab: a tag is one short name. No format character (an invisible
                // space, a direction mark): two tags never look the same
                'not_regex:/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u',
            ],
            'color' => ['sometimes', 'string', Rule::in(KnowiiMemberTagColor::toStringArray())],
            'visibility' => ['sometimes', 'string', Rule::in(KnowiiMemberTagVisibility::toStringArray())],
        ];
    }

    /**
     * The name is unique in the community without the case, as the index of the table: checked after the other rules
     *
     * @param  array{name?: string}  $validated
     *
     * @throws ValidationException
     */
    public static function ensureNameIsFree(Community $community, array $validated, ?int $ignoreId = null): void
    {
        if (array_key_exists('name', $validated) && self::nameTaken($community, $validated['name'], $ignoreId)) {
            throw ValidationException::withMessages(['name' => self::NAME_TAKEN]);
        }
    }

    public static function nameTaken(Community $community, string $name, ?int $ignoreId = null): bool
    {
        return MemberTag::query()
            ->where('community_id', $community->id)
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}

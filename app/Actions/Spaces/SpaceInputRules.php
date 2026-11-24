<?php

namespace App\Actions\Spaces;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use App\Models\Community;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation rules shared by the space actions. WARNING: aligned with space.schema.ts (libs/common).
 */
final class SpaceInputRules
{
    public const string PERSONAL_COMMUNITY_MESSAGE = 'A personal community has no public space';

    /**
     * @return array<string, list<string|object>>
     */
    public static function editable(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'min:'.Constants::MIN_LENGTH_SPACE_NAME, 'max:'.Constants::MAX_LENGTH_SPACE_NAME],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_SPACE_DESCRIPTION],
            // One emoji (possibly with modifiers), not text
            'emoji' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_SPACE_EMOJI, 'not_regex:/[\p{L}\p{N}<>]/u'],
            'visibility' => [$presence, Rule::enum(KnowiiSpaceVisibility::class)],
            'posting_policy' => [$presence, Rule::enum(KnowiiSpacePostingPolicy::class)],
            'space_group_cuid' => [$presence, 'string'],
            // The access groups of a "groups" space (#1241): cuids of groups of the community
            'access_groups' => ['sometimes', 'array', 'max:20'],
            'access_groups.*' => ['string'],
            // Link spaces only (#1294, ADR 0026): the page outside Knowii that the navigation opens
            'url' => ['sometimes', 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_SPACE_URL],
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function create(): array
    {
        return [
            ...self::editable(true),
            'type' => ['required', Rule::enum(KnowiiSpaceType::class)],
            // A link space holds no content: nobody posts in it, its posting policy is the default
            'posting_policy' => ['required_unless:type,'.KnowiiSpaceType::Link->value, Rule::enum(KnowiiSpacePostingPolicy::class)],
            'url' => ['required_if:type,'.KnowiiSpaceType::Link->value, 'prohibited_unless:type,'.KnowiiSpaceType::Link->value, 'string', 'url:http,https', 'max:'.Constants::MAX_LENGTH_SPACE_URL],
        ];
    }

    /**
     * A space of a personal community is never public: a personal community is the place of one person and has no
     * public page (PublicCommunity, ADR 0044). Called by the create and the update of a space, after the validation,
     * with the visibility of the request (null: the request does not change it).
     *
     * @throws ValidationException
     */
    public static function refusePublicInPersonalCommunity(Community $community, ?string $visibility): void
    {
        if ($visibility === KnowiiSpaceVisibility::Public->value && $community->visibility === KnowiiCommunityVisibility::Personal) {
            throw ValidationException::withMessages(['visibility' => self::PERSONAL_COMMUNITY_MESSAGE]);
        }
    }
}

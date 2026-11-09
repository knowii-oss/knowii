<?php

namespace App\Actions\Spaces;

use App\Constants;
use App\Enums\KnowiiSpacePostingPolicy;
use App\Enums\KnowiiSpaceType;
use App\Enums\KnowiiSpaceVisibility;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the space actions. WARNING: aligned with space.schema.ts (libs/common).
 */
final class SpaceInputRules
{
    /**
     * @return array<string, mixed>
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function create(): array
    {
        return [
            ...self::editable(true),
            'type' => ['required', Rule::enum(KnowiiSpaceType::class)],
        ];
    }
}

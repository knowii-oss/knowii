<?php

namespace App\Actions\AccessGroups;

use App\Constants;
use App\Models\Community;
use Illuminate\Validation\Rule;

final class AccessGroupInputRules
{
    /**
     * @return array<string, list<string|object>>
     */
    public static function editable(Community $community, bool $required, ?int $ignoreId = null): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name' => [
                $presence, 'string', 'min:'.Constants::MIN_LENGTH_ACCESS_GROUP_NAME, 'max:'.Constants::MAX_LENGTH_ACCESS_GROUP_NAME,
                Rule::unique('access_groups', 'name')->where('community_id', $community->id)->ignore($ignoreId),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_ACCESS_GROUP_DESCRIPTION],
        ];
    }

    public static function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

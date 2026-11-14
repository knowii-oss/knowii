<?php

namespace App\Support;

use App\Constants;

/**
 * The profile fields that a member shows to or hides from the other members of their communities (#1076, ADR 0020).
 * The email address is never shown. WARNING: aligned with PROFILE_VISIBILITY_FIELDS (user.schema.ts)
 */
final class ProfileFieldVisibility
{
    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return ['bio', 'location', 'phone', ...Constants::SOCIAL_MEDIA_LINK_PROPERTIES];
    }

    /**
     * Before a member chooses: what members saw before #1076. The phone stays private until the member shows it.
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        $defaults = array_fill_keys(self::fields(), true);
        $defaults['phone'] = false;

        return $defaults;
    }

    /**
     * The stored choices on top of the defaults; unknown keys are dropped.
     *
     * @param  array<mixed>|null  $stored  the stored choices, or the input of the profile form: narrowed here
     * @return array<string, bool>
     */
    public static function resolve(?array $stored): array
    {
        $visibility = self::defaults();
        foreach ($stored ?? [] as $field => $visible) {
            if (is_string($field) && array_key_exists($field, $visibility)) {
                $visibility[$field] = (bool) $visible;
            }
        }

        return $visibility;
    }
}

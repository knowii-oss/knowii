<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The emoji that members react with (#1204, ADR 0032). resources/emoji/allowed.json lists every accepted emoji, with
 * the skin tones, generated from Unicode data by scripts/generate-emoji-data.mjs (the picker reads the same data).
 * Free text is never accepted.
 */
final class Emoji
{
    /**
     * The quick reactions, first in the picker. WARNING: aligned with QUICK_REACTIONS (reaction.schema.ts)
     */
    public const array QUICK = ['👍', '❤️', '💡', '😄', '🎉'];

    /**
     * Different emoji on one item, at most: more is noise
     */
    public const int MAX_PER_ITEM = 20;

    /**
     * The emoji that the picker shows first as "Frequently used"
     */
    public const int FREQUENT_COUNT = 16;

    /**
     * @var array<string, true>|null
     */
    private static ?array $allowed = null;

    public static function isAllowed(mixed $emoji): bool
    {
        return is_string($emoji) && isset(self::allowed()[$emoji]);
    }

    /**
     * @return array<string, true>
     */
    private static function allowed(): array
    {
        if (self::$allowed === null) {
            /** @var list<string> $list */
            $list = json_decode(File::get(resource_path('emoji/allowed.json')), true, 2, JSON_THROW_ON_ERROR);
            self::$allowed = array_fill_keys($list, true);
        }

        return self::$allowed;
    }
}

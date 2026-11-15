<?php

namespace App\Support;

/**
 * A short plain-text excerpt of a Markdown or HTML text: no HTML, no Markdown signs, one line.
 */
final class Excerpt
{
    public static function of(string $text, int $length): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace(['/!?\[([^\]]*)\]\([^)]*\)/', '/[*_`>#~\[\]]+/', '/\s+/u'], ['$1', '', ' '], $text);
        $text = trim($text);

        return mb_strlen($text) > $length
            ? rtrim(mb_substr($text, 0, $length - 1)).'…'
            : $text;
    }
}

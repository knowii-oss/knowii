<?php

namespace App\Support;

/**
 * The headline of a profile (#1688): one line of plain text that a member writes about themselves. This class cleans what
 * the member typed, before the text is counted and stored. It does not touch HTML or Markdown: the pages show the
 * headline as text, so "I <3 notes" stays what the member wrote.
 */
final class ProfileHeadline
{
    /**
     * The characters that change the direction of the text: the embeddings and overrides (U+202A to U+202E) and the
     * isolates (U+2066 to U+2069). In a line that shows next to a name, they turn the text of the page around.
     */
    private const string DIRECTION_CHARACTERS = '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u';

    /**
     * The control characters (category Cc) that are not white space: the tab and the line breaks become a space below
     */
    private const string CONTROL_CHARACTERS = '/[^\P{Cc}\t\r\n]+/u';

    /**
     * The characters that show nothing and are not white space: the zero width space (U+200B), the word joiner (U+2060),
     * the byte order mark (U+FEFF), the Hangul filler (U+3164) and the blank braille pattern (U+2800). The two zero width
     * joiners have their own rule below.
     */
    private const string INVISIBLE_CHARACTERS = '/[\x{200B}\x{2060}\x{FEFF}\x{3164}\x{2800}]+/u';

    /**
     * The scripts whose words need a zero width non-joiner (U+200C) or a zero width joiner (U+200D) inside them: Persian
     * and the other languages of the Arabic script, the scripts of India, and some scripts of South-East and Central Asia
     */
    private const string JOINER_SCRIPTS = '\p{Arabic}\p{Devanagari}\p{Bengali}\p{Gurmukhi}\p{Gujarati}\p{Oriya}\p{Tamil}\p{Telugu}\p{Kannada}\p{Malayalam}\p{Sinhala}\p{Khmer}\p{Myanmar}\p{Mongolian}\p{Tibetan}';

    /**
     * A zero width non-joiner or joiner that joins nothing. Two kinds stay, and the expression skips them: one between
     * two characters of a script that needs it (a Persian or a Hindi word stays whole), and a joiner between two emoji,
     * the first with or without a variation selector (U+FE0F) or a skin tone (U+1F3FB to U+1F3FF): without that joiner,
     * a family or the rainbow flag falls apart into its parts. Every other one goes: between two Latin letters it only
     * hides a difference between two texts that look the same.
     */
    private const string LONE_JOINERS = '/(?:(?<=['.self::JOINER_SCRIPTS.'])[\x{200C}\x{200D}](?=['.self::JOINER_SCRIPTS.'])|(?<=\p{Extended_Pictographic}|\p{Extended_Pictographic}[\x{FE0F}\x{1F3FB}-\x{1F3FF}])\x{200D}(?=\p{Extended_Pictographic}))(*SKIP)(*FAIL)|[\x{200C}\x{200D}]/u';

    /**
     * A run of white space: tab, line break, line separator, paragraph separator, and every space separator of Unicode
     * (the category Zs: the space, the no-break space, the em space U+2003, the ideographic space U+3000 and the like)
     */
    private const string WHITE_SPACE = '/[\p{Zs}\t\r\n\x{2028}\x{2029}]+/u';

    /**
     * One clean line, or null when nothing stays: no character that changes the direction of the text, no control
     * character, no invisible character, one plain space (U+0020) for each run of white space, and no space at the
     * start or at the end. A zero width joiner stays between two emoji, a zero width joiner or non-joiner stays inside a
     * word of a script that needs it, and the variation selectors stay. Cleaning a clean text changes nothing.
     */
    public static function clean(string $text): ?string
    {
        // A text that is not valid UTF-8 would make each expression fail: its bad bytes become "?"
        $text = mb_scrub($text, 'UTF-8');

        $text = preg_replace(self::DIRECTION_CHARACTERS, '', $text) ?? '';
        $text = preg_replace(self::CONTROL_CHARACTERS, '', $text) ?? '';
        $text = preg_replace(self::INVISIBLE_CHARACTERS, '', $text) ?? '';
        // After the lines above: a joiner that an invisible character kept apart from its neighbor is a joiner to keep
        $text = preg_replace(self::LONE_JOINERS, '', $text) ?? '';
        $text = preg_replace(self::WHITE_SPACE, ' ', $text) ?? '';
        $text = trim($text, ' ');

        return $text === '' ? null : $text;
    }
}

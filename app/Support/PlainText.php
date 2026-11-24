<?php

namespace App\Support;

/**
 * The cleaning of a text that a person typed and that the pages show as plain text (ADR 0069). A name, a title or a place
 * is one line (oneLine()); a description or a bio keeps its line breaks (lines()). The class does not touch HTML or
 * Markdown: the pages show these texts as text, so "I <3 notes" stays what the person wrote.
 */
final class PlainText
{
    /**
     * The characters that change the direction of the text: the direction marks (U+200E, U+200F and the Arabic letter
     * mark U+061C), the embeddings and overrides (U+202A to U+202E) and the isolates (U+2066 to U+2069). In a line that
     * shows next to a name, they turn the text of the page around.
     */
    private const string DIRECTION_CHARACTERS = '/[\x{200E}\x{200F}\x{061C}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u';

    /**
     * The control characters (category Cc) that are not white space: the tab and the line breaks become a space below
     */
    private const string CONTROL_CHARACTERS = '/[^\P{Cc}\t\r\n]+/u';

    /**
     * The characters that show nothing and are not white space. Every format character (the category Cf: the zero width
     * space U+200B, the word joiner U+2060, the byte order mark U+FEFF, the soft hyphen U+00AD, the invisible operators of
     * mathematics U+2061 to U+2064, the tag characters U+E0001 to U+E007F, and the like), and the fillers of other
     * categories: the Hangul fillers (U+115F, U+1160, U+3164, U+FFA0), the blank braille pattern (U+2800), the combining
     * grapheme joiner (U+034F) and the two Khmer vowels that show nothing (U+17B4, U+17B5).
     *
     * Three kinds stay, and the expression skips them: the tags of a flag of a part of a country (the black flag U+1F3F4,
     * then tags, then the cancel tag U+E007F: the flags of England, Scotland and Wales), the Mongolian vowel separator
     * (U+180E) between two Mongolian letters, and the two zero width joiners, which have their own rule below.
     */
    private const string INVISIBLE_CHARACTERS = '/\x{1F3F4}[\x{E0020}-\x{E007E}]+\x{E007F}(*SKIP)(*FAIL)|(?<=\p{Mongolian})\x{180E}(?=\p{Mongolian})(*SKIP)(*FAIL)|[^\P{Cf}\x{200C}\x{200D}]|[\x{115F}\x{1160}\x{3164}\x{FFA0}\x{2800}\x{034F}\x{17B4}\x{17B5}]/u';

    /**
     * The scripts whose words need a zero width non-joiner (U+200C) or a zero width joiner (U+200D) inside them: Persian
     * and the other languages of the Arabic script, the scripts of India, and some scripts of South-East and Central Asia
     */
    private const string JOINER_SCRIPTS = '\p{Arabic}\p{Devanagari}\p{Bengali}\p{Gurmukhi}\p{Gujarati}\p{Oriya}\p{Tamil}\p{Telugu}\p{Kannada}\p{Malayalam}\p{Sinhala}\p{Khmer}\p{Myanmar}\p{Mongolian}\p{Tibetan}';

    /**
     * A zero width non-joiner or joiner that joins nothing. Two kinds stay, and the expression skips them: one after a
     * character of a script that needs it, before another one, a white space or the end of the text (a Persian or a Hindi
     * word stays whole, and so does a Malayalam or a Bengali word that ends with a joiner), and a joiner between two emoji,
     * the first with or without a variation selector (U+FE0F) or a skin tone (U+1F3FB to U+1F3FF): without that joiner,
     * a family or the rainbow flag falls apart into its parts. Every other one goes: between two Latin letters it only
     * hides a difference between two texts that look the same.
     */
    private const string LONE_JOINERS = '/(?:(?<=['.self::JOINER_SCRIPTS.'])[\x{200C}\x{200D}](?=['.self::JOINER_SCRIPTS.']|\s|$)|(?<=\p{Extended_Pictographic}|\p{Extended_Pictographic}[\x{FE0F}\x{1F3FB}-\x{1F3FF}])\x{200D}(?=\p{Extended_Pictographic}))(*SKIP)(*FAIL)|[\x{200C}\x{200D}]/u';

    /**
     * A run of white space: tab, line break, line separator, paragraph separator, and every space separator of Unicode
     * (the category Zs: the space, the no-break space, the em space U+2003, the ideographic space U+3000 and the like)
     */
    private const string WHITE_SPACE = '/[\p{Zs}\t\r\n\x{2028}\x{2029}]+/u';

    /**
     * Cleans the texts of the input of an action, before the validation counts them: the one-line fields with oneLine(),
     * the fields of several lines with lines(). A field with nothing left is null, so a required field fails as an empty
     * one. A value that is not a text stays as it is: the validation refuses it.
     *
     * The cleaning is in the action and not in the request, because an action is also called without a request.
     *
     * @param  array<mixed>  $input
     * @param  list<string>  $oneLine  The keys of the one-line fields
     * @param  list<string>  $lines  The keys of the fields of several lines
     */
    public static function clean(array &$input, array $oneLine = [], array $lines = []): void
    {
        foreach ($oneLine as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = self::oneLine($input[$key]);
            }
        }
        foreach ($lines as $key) {
            if (is_string($input[$key] ?? null)) {
                $text = self::lines($input[$key]);
                $input[$key] = $text === '' ? null : $text;
            }
        }
    }

    /**
     * One clean line, or null when nothing stays: no character that changes the direction of the text, no control
     * character, no invisible character, one plain space (U+0020) for each run of white space, and no space at the
     * start or at the end. A zero width joiner stays between two emoji, a zero width joiner or non-joiner stays after a
     * letter of a script that needs it, the Mongolian vowel separator stays between two Mongolian letters, the tags of the
     * flag of a part of a country stay, and the variation selectors stay. Cleaning a clean text changes nothing.
     */
    public static function oneLine(string $text): ?string
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

    /**
     * A text of several lines: no character that changes the direction of the text and no control character. The line
     * breaks stay, each as one line feed, and the tabs stay. Nothing else changes: the words, the spaces and the empty
     * lines are the ones of the person. Cleaning a clean text changes nothing.
     */
    public static function lines(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace(self::DIRECTION_CHARACTERS, '', $text) ?? '';

        return preg_replace(self::CONTROL_CHARACTERS, '', $text) ?? '';
    }
}

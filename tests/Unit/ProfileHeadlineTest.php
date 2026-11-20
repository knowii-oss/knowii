<?php

use App\Support\ProfileHeadline;

/*
 * The cleaning of the headline of a profile (#1688): one line of plain text. The class is pure: no application is needed.
 */

it('makes one clean line of a headline', function (string $typed, ?string $stored) {
    expect(ProfileHeadline::clean($typed))->toBe($stored);
})->with([
    'a plain text' => ['Product designer, writes about note-taking', 'Product designer, writes about note-taking'],
    'a line break' => ["Product designer\nwrites about note-taking", 'Product designer writes about note-taking'],
    'line breaks of another system' => ["a\r\n\r\nb", 'a b'],
    'a tab' => ["a\tb", 'a b'],
    'a no-break space' => ["a\u{00A0}b", 'a b'],
    'a line separator' => ["a\u{2028}b", 'a b'],
    'a paragraph separator' => ["a\u{2029}b", 'a b'],
    'several spaces' => ['a     b', 'a b'],
    'a mix of white space' => ["a \t\u{00A0}\r\n b", 'a b'],
    'wide spaces: two em spaces and an ideographic space' => ["a\u{2003}\u{2003}\u{3000}b", 'a b'],
    'every space separator of Unicode' => ["a\u{1680}\u{2000}\u{2001}\u{2002}\u{2004}\u{2005}\u{2006}\u{2007}\u{2008}\u{2009}\u{200A}\u{202F}\u{205F}b", 'a b'],
    'wide spaces at both ends' => ["\u{3000}\u{2003}a b\u{2003}\u{3000}", 'a b'],
    'spaces at both ends' => ['   a b   ', 'a b'],
    'white space of every kind at both ends' => ["\n\t\u{00A0} a b \u{2028}\r\n", 'a b'],
    'a null character and an escape character' => ["a\u{0000}b\u{001B}c", 'abc'],
    'a bell and a delete character' => ["a\u{0007}b\u{007F}c", 'abc'],
    'a control character between two spaces' => ["a \u{0000} b", 'a b'],
    'a character that turns the text around' => ["abc\u{202E}def", 'abcdef'],
    'every character that changes the direction' => ["\u{202A}a\u{202B}b\u{202C}c\u{202D}d\u{202E}e\u{2066}f\u{2067}g\u{2068}h\u{2069}", 'abcdefgh'],
    'a zero width space in a word' => ["a\u{200B}b", 'ab'],
    'every invisible character' => ["a\u{200B}b\u{200C}c\u{200D}d\u{2060}e\u{FEFF}f\u{3164}g\u{2800}h", 'abcdefgh'],
    'an invisible character between two spaces' => ["a \u{200B} b", 'a b'],
    'a zero width joiner between two letters' => ["a\u{200D}b", 'ab'],
    'a zero width non-joiner between two letters' => ["a\u{200C}b", 'ab'],
    'a zero width non-joiner inside a Persian word' => ["\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}", "\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}"],
    'a zero width joiner inside a Hindi word' => ["\u{0915}\u{094D}\u{200D}\u{0937}", "\u{0915}\u{094D}\u{200D}\u{0937}"],
    'a zero width non-joiner between a Latin and a Persian letter' => ["a\u{200C}\u{0645}", "a\u{0645}"],
    'a zero width non-joiner at the end of a Persian word' => ["\u{0645}\u{06CC}\u{200C}", "\u{0645}\u{06CC}"],
    'a zero width joiner next to one emoji only' => ["a\u{200D}\u{1F469} \u{1F469}\u{200D}b \u{1F469}\u{200D}", "a\u{1F469} \u{1F469}b \u{1F469}"],
    'two zero width joiners between two emoji' => ["\u{1F468}\u{200D}\u{200D}\u{1F469}", "\u{1F468}\u{1F469}"],
    'a family emoji' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}", "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"],
    'a flag of two letters' => ["From \u{1F1E7}\u{1F1EA}", "From \u{1F1E7}\u{1F1EA}"],
    'a flag with a zero width joiner' => ["\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}", "\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}"],
    'an emoji with a skin tone and a zero width joiner' => ["\u{1F469}\u{1F3FD}\u{200D}\u{1F4BB} developer", "\u{1F469}\u{1F3FD}\u{200D}\u{1F4BB} developer"],
    'an emoji with a zero width joiner' => ["Parent \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} of one", "Parent \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467} of one"],
    'an emoji with a variation selector' => ["I \u{2764}\u{FE0F} notes", "I \u{2764}\u{FE0F} notes"],
    'HTML and Markdown' => ['<b>Hi</b> & **you**', '<b>Hi</b> & **you**'],
    'a text that looks like a tag' => ['I <3 notes', 'I <3 notes'],
    'a web address' => ['See https://example.com', 'See https://example.com'],
    'letters with accents' => ['Zoé, développeuse à Liège, écrit sur le thé', 'Zoé, développeuse à Liège, écrit sur le thé'],
    'an empty text' => ['', null],
    'spaces only' => ['     ', null],
    'white space of every kind only' => [" \t\r\n\u{00A0}\u{2028}\u{2029} ", null],
    'only characters that the cleaning removes' => ["\u{202E}\u{0000}\u{2066}", null],
    'a zero width space only' => ["\u{200B}", null],
    'a zero width joiner only' => ["\u{200D}", null],
    'an ideographic space only' => ["\u{3000}", null],
    'a Hangul filler only' => ["\u{3164}", null],
    'a blank braille pattern only' => ["\u{2800}", null],
    'invisible characters and wide spaces only' => ["\u{3000}\u{200B}\u{200C}\u{200D}\u{2060}\u{FEFF}\u{3164}\u{2800}\u{2003}", null],
]);

it('gives the same text when it cleans a clean text again', function (string $typed) {
    $once = ProfileHeadline::clean($typed);

    expect($once)->not->toBeNull()
        ->and(ProfileHeadline::clean((string) $once))->toBe($once);
})->with([
    "  a \u{0000} b\r\n\r\nc\u{202E}  ",
    "\u{00A0}Product designer,\twrites about note-taking\u{2029}",
    "\u{3000}a\u{200B}\u{2003}\u{2003}b \u{1F468}\u{200B}\u{200D}\u{1F469}\u{200D}\u{200D}c\u{3164}",
]);

it('keeps whole each emoji that a zero width joiner holds together', function (string $emoji) {
    // One picture for the reader before the cleaning, and one after it: the plain removal of the joiner gives two or three
    expect(grapheme_strlen($emoji))->toBe(1)
        ->and(grapheme_strlen((string) ProfileHeadline::clean($emoji)))->toBe(1)
        ->and(ProfileHeadline::clean($emoji))->toBe($emoji);
})->with([
    'a family' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"],
    'the rainbow flag' => ["\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}"],
    'the flag of a country' => ["\u{1F1E7}\u{1F1EA}"],
    'a heart on fire' => ["\u{2764}\u{FE0F}\u{200D}\u{1F525}"],
]);

it('counts each code point of a cleaned text', function () {
    $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";

    expect(mb_strlen((string) ProfileHeadline::clean($family)))->toBe(5)
        ->and(mb_strlen((string) ProfileHeadline::clean(str_repeat("\u{1F600}", 80))))->toBe(80);
});

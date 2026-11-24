<?php

use App\Support\PlainText;

/*
 * The cleaning of a text that a person typed (ADR 0069): one line of plain text for a name, a title or the headline of a
 * profile (#1688), and plain text of several lines for a description or a bio. The class is pure: no application is needed.
 */

it('makes one clean line of a text', function (string $typed, ?string $stored) {
    expect(PlainText::oneLine($typed))->toBe($stored);
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
    'a left-to-right mark, a right-to-left mark and an Arabic letter mark' => ["Ad\u{200E}m\u{200F}i\u{061C}n", 'Admin'],
    'a soft hyphen' => ["Ad\u{00AD}min", 'Admin'],
    'the invisible operators of mathematics' => ["A\u{2061}d\u{2062}m\u{2063}i\u{2064}n", 'Admin'],
    'the old format characters' => ["A\u{206A}d\u{206B}m\u{206C}i\u{206D}n\u{206E}\u{206F}", 'Admin'],
    'a tag character outside a flag' => ["x\u{E0041}y\u{E0001}z\u{E007F}", 'xyz'],
    'a Mongolian vowel separator after a Latin letter' => ["Staff\u{180E}", 'Staff'],
    'the format characters of annotations, music and hieroglyphs' => ["a\u{FFF9}b\u{FFFA}c\u{FFFB}d\u{1D173}e\u{13430}f", 'abcdef'],
    'the Hangul fillers' => ["a\u{115F}b\u{1160}c\u{3164}d\u{FFA0}e", 'abcde'],
    'a combining grapheme joiner and the Khmer vowels that show nothing' => ["a\u{034F}b\u{17B4}c\u{17B5}d", 'abcd'],
    'a zero width non-joiner between two letters' => ["a\u{200C}b", 'ab'],
    'a zero width non-joiner inside a Persian word' => ["\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}", "\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}"],
    'a zero width joiner inside a Hindi word' => ["\u{0915}\u{094D}\u{200D}\u{0937}", "\u{0915}\u{094D}\u{200D}\u{0937}"],
    'a zero width non-joiner between a Latin and a Persian letter' => ["a\u{200C}\u{0645}", "a\u{0645}"],
    'a zero width non-joiner at the end of a Persian word' => ["\u{0645}\u{06CC}\u{200C}", "\u{0645}\u{06CC}\u{200C}"],
    'a zero width joiner at the end of a Malayalam word, before a space' => ["\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D} \u{0D30}\u{0D3E}\u{0D1C}", "\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D} \u{0D30}\u{0D3E}\u{0D1C}"],
    'a zero width joiner between a Devanagari and a Latin letter' => ["\u{0915}\u{200D}Admin", "\u{0915}Admin"],
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
    'two Hangul fillers only' => ["\u{115F}\u{1160}", null],
    'a halfwidth Hangul filler only' => ["\u{FFA0}", null],
    'two invisible operators only' => ["\u{2061}\u{2062}", null],
    'a soft hyphen only' => ["\u{00AD}", null],
    'a left-to-right mark only' => ["\u{200E}", null],
    'tag characters only' => ["\u{E0041}\u{E0042}", null],
    'invisible characters and wide spaces only' => ["\u{3000}\u{200B}\u{200C}\u{200D}\u{2060}\u{FEFF}\u{3164}\u{2800}\u{2003}", null],
]);

it('gives the same text when it cleans a clean text again', function (string $typed) {
    $once = PlainText::oneLine($typed);

    expect($once)->not->toBeNull()
        ->and(PlainText::oneLine((string) $once))->toBe($once);
})->with([
    "  a \u{0000} b\r\n\r\nc\u{202E}  ",
    "\u{00A0}Product designer,\twrites about note-taking\u{2029}",
    "\u{3000}a\u{200B}\u{2003}\u{2003}b \u{1F468}\u{200B}\u{200D}\u{1F469}\u{200D}\u{200D}c\u{3164}",
    "\u{1F3F4}\u{200B}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F} \u{1828}\u{200B}\u{180E}\u{1820} \u{0D28}\u{0D4D}\u{200D}\u{200B} x",
]);

it('keeps whole each emoji that a zero width joiner holds together', function (string $emoji) {
    // One picture for the reader before the cleaning, and one after it: the plain removal of the joiner gives two or three
    expect(grapheme_strlen($emoji))->toBe(1)
        ->and(grapheme_strlen((string) PlainText::oneLine($emoji)))->toBe(1)
        ->and(PlainText::oneLine($emoji))->toBe($emoji);
})->with([
    'a family' => ["\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}"],
    'the rainbow flag' => ["\u{1F3F3}\u{FE0F}\u{200D}\u{1F308}"],
    'the flag of a country' => ["\u{1F1E7}\u{1F1EA}"],
    'a heart on fire' => ["\u{2764}\u{FE0F}\u{200D}\u{1F525}"],
    'the flag of Scotland' => ["\u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}"],
]);

it('counts each code point of a cleaned text', function () {
    $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}";

    expect(mb_strlen((string) PlainText::oneLine($family)))->toBe(5)
        ->and(mb_strlen((string) PlainText::oneLine(str_repeat("\u{1F600}", 80))))->toBe(80);
});

it('keeps a normal name as the person wrote it', function (string $name) {
    expect(PlainText::oneLine($name))->toBe($name)
        ->and(PlainText::lines($name))->toBe($name);
})->with([
    'Latin letters with accents' => ['Zoé Lefèvre-Müller'],
    'an apostrophe and an ampersand' => ["Sam O'Neil & Sons"],
    'Arabic' => ["\u{0645}\u{062D}\u{0645}\u{062F} \u{0639}\u{0644}\u{064A}"],
    'Persian with a zero width non-joiner' => ["\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}"],
    'Hebrew' => ["\u{05D3}\u{05D5}\u{05D3} \u{05D1}\u{05DF}"],
    'Chinese' => ["\u{738B}\u{5C0F}\u{660E}"],
    'Japanese' => ["\u{5C71}\u{7530} \u{592A}\u{90CE}"],
    'Hindi' => ["\u{0930}\u{093E}\u{091C}\u{0947}\u{0936}"],
    'Cyrillic' => ["\u{0410}\u{043D}\u{043D}\u{0430} \u{041A}\u{0430}\u{0440}\u{0435}\u{043D}\u{0438}\u{043D}\u{0430}"],
    'an emoji with a zero width joiner' => ["Ada \u{1F469}\u{200D}\u{1F4BB}"],
    'an emoji with a skin tone' => ["Ada \u{1F44B}\u{1F3FD}"],
    'Hindi with a zero width joiner' => ["\u{0915}\u{094D}\u{200D}\u{0937}"],
    'Malayalam with a zero width joiner at the end of a word' => ["\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D}"],
    'Malayalam with a zero width joiner at the end of the first word' => ["\u{0D05}\u{0D35}\u{0D28}\u{0D4D}\u{200D} \u{0D30}\u{0D3E}\u{0D1C}"],
    'Bengali with a zero width joiner at the end of a word' => ["\u{09A4}\u{09CD}\u{200D}"],
    'Mongolian with a vowel separator' => ["\u{1828}\u{1820}\u{180E}\u{1820}"],
    'the flag of a country' => ["From \u{1F1E7}\u{1F1EA}"],
    'the flag of Scotland' => ["From \u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}"],
    'the flag of England after a name' => ["Ada \u{1F3F4}\u{E0067}\u{E0062}\u{E0065}\u{E006E}\u{E0067}\u{E007F} Lovelace"],
]);

it('keeps the line breaks of a text of several lines, and removes the control and the direction characters', function (string $typed, string $stored) {
    expect(PlainText::lines($typed))->toBe($stored)
        ->and(PlainText::lines($stored))->toBe($stored);
})->with([
    'a plain text' => ["I write about notes.\n\nI live in Liège.", "I write about notes.\n\nI live in Liège."],
    'line breaks of another system' => ["a\r\nb\rc", "a\nb\nc"],
    'a tab and several spaces' => ["a\t b   c", "a\t b   c"],
    'a character that turns the text around' => ["abc\u{202E}def\nghi", "abcdef\nghi"],
    'every character that changes the direction' => ["\u{202A}a\u{202B}b\u{202C}c\u{202D}d\u{202E}e\u{2066}f\u{2067}g\u{2068}h\u{2069}", 'abcdefgh'],
    'the direction marks' => ["a\u{200E}b\u{200F}c\u{061C}d\ne", "abcd\ne"],
    'a null character, a bell, an escape and a delete character' => ["a\u{0000}b\u{0007}c\u{001B}d\u{007F}e", 'abcde'],
    'a control character of the second set' => ["a\u{0085}b\u{009F}c", 'abc'],
    'an emoji with a zero width joiner' => ["Parent \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\nof one", "Parent \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\nof one"],
    'HTML and Markdown' => ["<b>Hi</b> & **you**\n# not a heading", "<b>Hi</b> & **you**\n# not a heading"],
    'only characters that the cleaning removes' => ["\u{202E}\u{0000}", ''],
]);

it('cleans the named fields of an input, and leaves the others', function () {
    $input = [
        'name' => "  Ada\u{202E}\r\nLovelace\u{200B} ",
        'title' => "\u{200B}\u{0000}",
        'description' => "One\r\nTwo\u{202E}",
        'notes' => "\u{0000}",
        'body' => "Kept\u{202E}\r\nas it is",
        'count' => 3,
        'list' => ['a'],
        'nothing' => null,
    ];

    PlainText::clean($input, ['name', 'title', 'count', 'list', 'nothing', 'absent'], ['description', 'notes']);

    expect($input)->toBe([
        'name' => 'Ada Lovelace',
        'title' => null,
        'description' => "One\nTwo",
        'notes' => null,
        'body' => "Kept\u{202E}\r\nas it is",
        'count' => 3,
        'list' => ['a'],
        'nothing' => null,
    ]);
});

<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A text of a Markdown mail that holds a value of a person: a name, the name of a community, an address typed in a form
 * (ADR 0069). The mail prints it as one line of plain text, as the person wrote it: no link, no image, no heading, no
 * emphasis, no list and no HTML.
 *
 * In a mail view:
 *
 *     {{ App\Support\MailText::plain(__(':name invited you.', ['name' => $inviterName])) }}
 *
 * How: the text becomes one line (PlainText::oneLine()), then each character of the punctuation of ASCII is written as a
 * numeric character reference ("&#91;" for "["). Markdown reads a character reference as the character itself and never
 * as a part of its syntax, and the plain-text part of the mail decodes it. So the two parts of the mail show the same
 * characters, and neither one shows a backslash or a reference.
 */
final class MailText
{
    /**
     * The punctuation and the symbols of ASCII: every character that can start or end a piece of Markdown or of HTML
     */
    private const string PUNCTUATION = '/[!-\/:-@\[-`{-~]/';

    /**
     * The text as one line of plain text, ready for "{{ }}" in a Markdown mail view
     */
    public static function plain(?string $text): HtmlString
    {
        $line = PlainText::oneLine($text ?? '') ?? '';

        return new HtmlString(preg_replace_callback(self::PUNCTUATION, static fn (array $match): string => '&#'.ord($match[0]).';', $line) ?? '');
    }

    /**
     * An address for an attribute of a Markdown mail view: the address of a button (the "url" of "mail::button") or of a
     * link. In a Markdown mail, "{{ }}" escapes no quote (Markdown::withSecuredEncoding()), so an attribute that a view
     * fills with "{{ }}" ends at the first quote of its value. This escapes the value for HTML, quotes included; the
     * plain-text part of the mail decodes it. A view that writes the attribute itself uses "{!! e($value) !!}".
     */
    public static function address(?string $url): HtmlString
    {
        return new HtmlString(e($url ?? ''));
    }

    /**
     * A value of a person for the subject of a mail: one line, no control character
     */
    public static function subject(?string $text): string
    {
        return PlainText::oneLine($text ?? '') ?? '';
    }
}

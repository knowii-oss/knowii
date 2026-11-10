<?php

namespace App\Enums;

/**
 * The rows of a page space or a course space (#1188, ADR 0029). WARNING: aligned with pageKindSchema (page.schema.ts)
 */
enum KnowiiPageKind: string
{
    // A page, or a lesson in a course space: a title, Markdown, an optional video (courses)
    case Page = 'page';
    // A heading that groups the pages after it, up to the next section: a title only, no page of its own
    case Section = 'section';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

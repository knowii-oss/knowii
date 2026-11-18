<?php

namespace App\Enums;

// WARNING: Must remain in sync with the one defined in space.schema.ts (libs/common)
enum KnowiiSpaceType: string
{
    case Discussion = 'discussion';
    case Chat = 'chat';
    case Pages = 'pages';
    case Course = 'course';
    case Resources = 'resources';
    // A navigation link to a page outside Knowii (#1294, ADR 0026): no content, the navigation opens its URL
    case Link = 'link';
    // Dated events with a host (#1513, ADR 0053): a table of its own, `events`
    case Events = 'events';

    /**
     * Page spaces and course spaces hold ordered pages: a lesson of a course is a page (#1188, ADR 0029)
     */
    public function holdsPages(): bool
    {
        return $this === self::Pages || $this === self::Course;
    }

    public static function toCommaSeparatedString(): string
    {
        return implode(',', array_column(self::cases(), 'value'));
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

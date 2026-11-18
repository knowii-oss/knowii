<?php

namespace App\Enums;

/**
 * The state of an event (#1513, ADR 0053). "Past" is not a state: an event is past when its end is before now.
 * WARNING: aligned with eventStateSchema (event.schema.ts)
 */
enum KnowiiEventState: string
{
    // Seen by its host, the owner and the admins only. Tells nobody
    case Draft = 'draft';
    // Seen by the people who can read the space
    case Published = 'published';
    // Still seen, with the word "Cancelled", so that a member who has a link finds the news
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

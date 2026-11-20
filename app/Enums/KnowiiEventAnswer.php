<?php

namespace App\Enums;

/**
 * The answer of a member to an event (#1515, ADR 0053). No row means no answer. "Replay only" is offered only when the
 * host says that a replay will exist. WARNING: aligned with EVENT_ANSWERS (event-answer.schema.ts)
 */
enum KnowiiEventAnswer: string
{
    // The member comes: the only answer that takes a place
    case Yes = 'yes';
    // The member cannot come and wants the replay
    case Replay = 'replay';
    case No = 'no';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

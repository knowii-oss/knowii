<?php

namespace App\Enums;

/**
 * The result of one row of an import of invitations (#1390, ADR 0061): `valid` and `refused` in a preview; `queued`,
 * `sent`, `held`, `failed`, `skipped` and `refused` after the confirm.
 * WARNING: aligned with INVITATION_IMPORT_OUTCOMES (invitation-import.schema.ts) and the check of the migration
 */
enum KnowiiInvitationImportOutcome: string
{
    // The row can be invited (a preview)
    case Valid = 'valid';
    // The row is not invited: its reason says why
    case Refused = 'refused';
    // The invitation exists and its mail waits on the queue
    case Queued = 'queued';
    // The mail went out
    case Sent = 'sent';
    // The mail budget of the instance held the mail: "Send the mails that were not sent" sends it later
    case Held = 'held';
    // The mail failed after its retries
    case Failed = 'failed';
    // No mail: the invitation was cancelled or accepted, or the person joined meanwhile
    case Skipped = 'skipped';

    /**
     * The row waits for nothing any more
     *
     * @return list<self>
     */
    public static function ended(): array
    {
        return [self::Sent, self::Held, self::Failed, self::Skipped];
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

/**
 * The state of an import of invitations from a file (#1390, ADR 0061).
 * WARNING: aligned with INVITATION_IMPORT_STATES (invitation-import.schema.ts) and the check of the migration
 */
enum KnowiiInvitationImportState: string
{
    // The file was read and checked: the manager who uploaded it sends it or discards it. Kept 24 hours
    case Preview = 'preview';
    // Sent: the mails of the rows go out one by one
    case Sending = 'sending';
    // No row waits for its mail any more
    case Done = 'done';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Why a row of an import of invitations is refused (#1390, ADR 0061). A row has one reason: the first of the cases, in
 * this order. `text()` gives the words of the API (`reason_text`).
 * WARNING: aligned with INVITATION_IMPORT_REFUSALS (invitation-import.schema.ts) and the check of the migration
 */
enum KnowiiInvitationImportRefusal: string
{
    case ExtraCells = 'extra_cells';
    case CellTooLong = 'cell_too_long';
    case InvalidEmail = 'invalid_email';
    // The same address on an earlier row: `detail` holds the number of that row
    case DuplicateInFile = 'duplicate_in_file';
    case UnknownRole = 'unknown_role';
    case RoleNotAllowed = 'role_not_allowed';
    case AccessGroupsNotAllowed = 'access_groups_not_allowed';
    // `detail` holds the name, cut to 64 characters
    case UnknownAccessGroup = 'unknown_access_group';
    case AmbiguousAccessGroup = 'ambiguous_access_group';
    case TagsNotAllowed = 'tags_not_allowed';
    // `detail` holds the name, cut to 64 characters
    case UnknownTag = 'unknown_tag';
    case TooManyTags = 'too_many_tags';
    // A member of the community, the owner too
    case AlreadyMember = 'already_member';
    // An account with this address has a join block in this community: a manager removed the person
    case RemovedByManager = 'removed_by_manager';
    // A pending invitation of this community that is not expired: `detail` holds the time of its end (ISO 8601)
    case PendingInvitation = 'pending_invitation';

    /**
     * The words of the reason, with the detail of the row
     */
    public function text(?string $detail): string
    {
        return match ($this) {
            self::ExtraCells => 'This row has more cells than the header',
            self::CellTooLong => 'A cell holds more than 1000 characters',
            self::InvalidEmail => 'This is not a valid email address',
            self::DuplicateInFile => 'This address is on row '.($detail ?? '?').' already',
            self::UnknownRole => 'Use member, moderator or admin',
            self::RoleNotAllowed => 'You cannot give this role',
            self::AccessGroupsNotAllowed => 'You cannot give access groups',
            self::UnknownAccessGroup => 'No access group is named '.($detail ?? ''),
            self::AmbiguousAccessGroup => 'Two access groups have this name in another case. Write the name exactly.',
            self::TagsNotAllowed => 'You cannot give member tags',
            self::UnknownTag => 'No member tag is named '.($detail ?? ''),
            self::TooManyTags => 'An invitation holds at most 20 tags',
            self::AlreadyMember => 'This person is already a member',
            self::RemovedByManager => 'A manager removed this person. Invite them one at a time if they may come back.',
            self::PendingInvitation => 'This address has a pending invitation. It is valid until '.self::date($detail).'.',
        };
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    private static function date(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '?';
        }

        return CarbonImmutable::parse($iso)->utc()->format('Y-m-d H:i').' UTC';
    }
}

<?php

namespace App\Support\InvitationImports;

use App\Enums\KnowiiCommunityMemberRole;
use App\Enums\KnowiiInvitationImportRefusal;
use App\Models\AccessGroup;
use App\Models\MemberTag;

/**
 * The result of the checks of one row (#1390, ADR 0061): the address to store, the role, the groups and the tags with
 * the defaults, and the reason when the row is refused.
 */
final class InvitationImportCheckedRow
{
    public ?KnowiiCommunityMemberRole $role = null;

    /**
     * @var list<AccessGroup>
     */
    public array $groups = [];

    /**
     * @var list<MemberTag>
     */
    public array $tags = [];

    /**
     * @var list<string>
     */
    public array $groupNames = [];

    /**
     * @var list<string>
     */
    public array $tagNames = [];

    public ?KnowiiInvitationImportRefusal $reason = null;

    public ?string $detail = null;

    public function __construct(public readonly int $rowNumber, public readonly string $email) {}
}

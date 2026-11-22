<?php

namespace App\Support;

use App\Constants;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\MemberTag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of `member_tag_members` (#1389, ADR 0060): the actions of the tags, and later the invitations and the
 * invite links (#1390, #1391), put a tag on a member and take it off here. It checks no gate: the caller did.
 *
 * A link needs a member of the community of the tag (the owner counts), and a member has at most
 * Constants::MAX_MEMBER_TAGS_PER_MEMBER tags in one community. Each change locks the membership row of the member (the
 * community row for the owner) first, so two changes at once cannot pass the limit, and a member who leaves at the same
 * moment keeps no tag.
 */
final class MemberTagAssignments
{
    /**
     * Put the tag on the member. True when the link is new, false when the member had the tag already (nothing changes).
     *
     * @throws ValidationException when the person is not a member of the community of the tag (on the field `member`)
     * @throws BusinessException when the member has the most tags already
     */
    public static function add(MemberTag $tag, User $member, string $field = 'member'): bool
    {
        return DB::transaction(static function () use ($tag, $member, $field): bool {
            $community = $tag->community;
            if (! self::lockMembership($community, $member)) {
                throw ValidationException::withMessages([$field => 'No member of the community matches']);
            }

            $links = DB::table('member_tag_members');
            if ((clone $links)->where('member_tag_id', $tag->id)->where('user_id', $member->id)->exists()) {
                return false;
            }

            $count = (clone $links)
                ->where('user_id', $member->id)
                ->whereIn('member_tag_id', MemberTag::query()->where('community_id', $community->id)->select('id'))
                ->count();
            if ($count >= Constants::MAX_MEMBER_TAGS_PER_MEMBER) {
                throw new BusinessException('A member can have at most '.Constants::MAX_MEMBER_TAGS_PER_MEMBER.' tags');
            }

            (clone $links)->insert(['member_tag_id' => $tag->id, 'user_id' => $member->id, 'created_at' => now()]);

            return true;
        });
    }

    /**
     * Take the tag off the member. True when the member had it, false when not (nothing changes).
     */
    public static function remove(MemberTag $tag, User $member): bool
    {
        return DB::table('member_tag_members')->where('member_tag_id', $tag->id)->where('user_id', $member->id)->delete() > 0;
    }

    /**
     * Lock the row that makes the person a member of the community: their membership row, or the community row for the
     * owner, who has none. False when the person is not a member.
     */
    private static function lockMembership(Community $community, User $member): bool
    {
        // The community row: a transfer of the ownership at the same moment waits, or came first (the former owner then
        // has a membership row)
        if ($community->owner_id === $member->id
            && Community::query()->whereKey($community->id)->lockForUpdate()->first(['id', 'owner_id'])?->owner_id === $member->id) {
            return true;
        }

        return CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('user_id', $member->id)
            ->lockForUpdate()
            ->first(['id']) !== null;
    }
}

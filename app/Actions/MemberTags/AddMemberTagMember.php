<?php

namespace App\Actions\MemberTags;

use App\Contracts\MemberTags\AddsMemberTagMembers;
use App\Models\MemberTag;
use App\Models\User;
use App\Support\MemberTagAssignments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddMemberTagMember implements AddsMemberTagMembers
{
    /**
     * Put a member tag on a member of the community (#1389, ADR 0060): the owner, the admins, and the moderators with the
     * "manage members" right, on any member, themself too (a tag gives no right). The member is named by exactly one of
     * member (user cuid) or username; never by an email address. Putting the same tag twice changes nothing. Nothing tells
     * the member: no notification, no email, no broadcast.
     */
    final public function add(User $user, MemberTag $tag, array $input): User
    {
        $community = $tag->community;
        Gate::forUser($user)->authorize('assignMemberTags', $community);

        /** @var array{member?: string, username?: string} $validated */
        $validated = Validator::make($input, [
            'member' => ['required_without:username', 'prohibits:username', 'string'],
            'username' => ['required_without:member', 'prohibits:member', 'string'],
            // No sync by address is planned: the import of #1390 reads the addresses in its own action
            'email' => ['prohibited'],
        ])->validate();

        $field = array_key_exists('member', $validated) ? 'member' : 'username';
        $member = User::query()->where($field === 'member' ? 'cuid' : 'username', $validated[$field] ?? '')->first();
        // The error never says whether the person has an account
        if ($member === null) {
            throw ValidationException::withMessages([$field => 'No member of the community matches']);
        }

        $added = MemberTagAssignments::add($tag, $member, $field);

        if ($added) {
            Log::info('Member tag added to a member', ['community' => $community->cuid, 'tag' => $tag->cuid, 'member' => $member->cuid]);
        }

        return $member;
    }
}

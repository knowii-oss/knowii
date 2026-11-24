<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\User;
use App\Support\AccessGroupMemberships;
use App\Support\CommunityRoles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddAccessGroupMember implements AddsAccessGroupMembers
{
    public const EMAIL_SELECTOR_MESSAGE = 'Only the owner and the administrators add a member by email address. Use the username.';

    /**
     * Add a member of the community to an access group (#1241, ADR 0025). The member is named by exactly one of
     * member (user cuid), username or email, so that a sync from another system (the external account service) can
     * use the email address it knows. Adding a member twice changes nothing. The membership is marked as added by hand
     * (`manual`): it stays when a rule of the group stops giving it (#1386).
     *
     * The selectors `member` and `username` return the member, and refuse a person who is not a member of the
     * community. The selector `email` returns nothing, and has one result for the caller whether a member of the
     * community has the address or not (nothing is added then): the email address of a member is never shown to
     * another member (ADR 0020), and a sync can send its whole list again. The selector `email` is for the owner and
     * the admins only (a token follows the role of its account): a moderator adds by cuid or username, and the refusal
     * comes before the address is read.
     *
     * @throws AuthorizationException
     */
    final public function add(User $user, AccessGroup $group, array $input): ?User
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroupMembers', $community);

        /** @var array{member?: string, username?: string, email?: string} $validated */
        $validated = Validator::make($input, [
            'member' => ['required_without_all:username,email', 'prohibits:username,email', 'string'],
            'username' => ['required_without_all:member,email', 'prohibits:member,email', 'string'],
            'email' => ['required_without_all:member,username', 'prohibits:member,username', 'string', 'email'],
        ])->validate();

        $field = array_key_first($validated) ?? 'member';
        $byEmail = $field === 'email';

        if ($byEmail && ! CommunityRoles::isOwnerOrAdmin($user, $community)) {
            throw new AuthorizationException(self::EMAIL_SELECTOR_MESSAGE);
        }

        $member = self::findMember($community, $field, $validated);

        if ($member === null) {
            if ($byEmail) {
                Log::info('Access group member not added: no member of the community has the address', ['group' => $group->cuid]);

                return null;
            }

            throw ValidationException::withMessages([$field => 'No member of the community matches']);
        }

        // By hand: the `manual` flag. A member whom a rule already gave the group keeps that source too (#1386, ADR 0047)
        AccessGroupMemberships::addManual($group, $member);

        Log::info('Access group member added', ['group' => $group->cuid, 'member' => $member->cuid]);
        AccessGroupsChanged::dispatch($community->cuid);
        SpaceTreeChanged::dispatch($community->cuid);

        return $byEmail ? null : $member;
    }

    /**
     * Only a member of the community can join one of its groups. Nothing here says whether the person has an account.
     *
     * @param  array{member?: string, username?: string, email?: string}  $validated
     */
    private static function findMember(Community $community, string $field, array $validated): ?User
    {
        $query = User::query();
        match ($field) {
            'member' => $query->where('cuid', $validated['member'] ?? ''),
            'username' => $query->where('username', $validated['username'] ?? ''),
            default => $query->whereRaw('lower(email) = ?', [strtolower($validated['email'] ?? '')]),
        };
        $member = $query->first();

        return $member !== null && $member->belongsToCommunity($community) ? $member : null;
    }
}

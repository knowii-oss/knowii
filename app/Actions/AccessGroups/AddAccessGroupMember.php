<?php

namespace App\Actions\AccessGroups;

use App\Contracts\AccessGroups\AddsAccessGroupMembers;
use App\Events\AccessGroups\AccessGroupsChanged;
use App\Events\Spaces\SpaceTreeChanged;
use App\Models\AccessGroup;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddAccessGroupMember implements AddsAccessGroupMembers
{
    /**
     * Add a member of the community to an access group (#1241, ADR 0025). The member is named by exactly one of
     * member (user cuid), username or email, so that a sync from another system (the Knowii account) can use the email
     * address it knows. Adding a member twice changes nothing.
     */
    final public function add(User $user, AccessGroup $group, array $input): User
    {
        $community = $group->community;
        Gate::forUser($user)->authorize('manageAccessGroupMembers', $community);

        /** @var array{member?: string, username?: string, email?: string} $validated */
        $validated = Validator::make($input, [
            'member' => ['required_without_all:username,email', 'prohibits:username,email', 'string'],
            'username' => ['required_without_all:member,email', 'prohibits:member,email', 'string'],
            'email' => ['required_without_all:member,username', 'prohibits:member,username', 'string', 'email'],
        ])->validate();

        $member = self::findMember($community, $validated);

        $group->members()->syncWithoutDetaching([$member->id]);

        Log::info('Access group member added', ['group' => $group->cuid, 'member' => $member->cuid]);
        AccessGroupsChanged::dispatch($community->cuid);
        SpaceTreeChanged::dispatch($community->cuid);

        return $member;
    }

    /**
     * Only a member of the community can join one of its groups. The error never says whether the person has an account.
     *
     * @param  array{member?: string, username?: string, email?: string}  $validated
     */
    private static function findMember(Community $community, array $validated): User
    {
        $field = array_key_first($validated) ?? 'member';
        $query = User::query();
        match ($field) {
            'member' => $query->where('cuid', $validated['member'] ?? ''),
            'username' => $query->where('username', $validated['username'] ?? ''),
            default => $query->whereRaw('lower(email) = ?', [strtolower($validated['email'] ?? '')]),
        };
        $member = $query->first();

        if ($member === null || ! $member->belongsToCommunity($community)) {
            throw ValidationException::withMessages([$field => 'No member of the community matches']);
        }

        return $member;
    }
}

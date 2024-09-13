<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\InvitesCommunityMembers;
use App\Events\Communities\InvitingCommunityMember;
use App\Knowii;
use App\Mail\CommunityInvitation;
use App\Models\Community;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class InviteCommunityMember implements InvitesCommunityMembers
{
    /**
     * Invite a new member to the given community.
     */
    public function invite(User $user, Community $community, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);

        $this->validate($community, $email, $role);

        InvitingCommunityMember::dispatch($community, $email, $role);

        $invitation = $community->communityInvitations()->create([
            'email' => $email,
            'role' => $role,
        ]);

        Mail::to($email)->send(new CommunityInvitation($invitation));
    }

    /**
     * Validate the invite member operation.
     */
    protected function validate(Community $community, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules($community), [
            'email.unique' => __('This user has already been invited to the community.'),
        ])->after(
            $this->ensureUserIsNotAlreadyInCommunity($community, $email)
        );
    }

    /**
     * Get the validation rules for inviting a community member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(Community $community): array
    {
        return array_filter([
            'email' => [
                'required', 'email',
                Rule::unique(Knowii::communityInvitationModel())->where(function (Builder $query) use ($community) {
                    $query->where('community_id', $community->id);
                }),
            ],
            'role' => Jetstream::hasRoles()
                            ? ['required', 'string', new Role]
                            : null,
        ]);
    }

    /**
     * Ensure that the user is not already in the community.
     */
    protected function ensureUserIsNotAlreadyInCommunity(Community $community, string $email): Closure
    {
        return function ($validator) use ($community, $email) {
            $validator->errors()->addIf(
                $community->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the community.')
            );
        };
    }
}

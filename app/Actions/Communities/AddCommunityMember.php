<?php

namespace App\Actions\Communities;

use App\Contracts\Communities\AddsCommunityMembers;
use App\Events\Communities\AddingCommunityMember;
use App\Events\Communities\CommunityMemberAdded;
use App\Models\Community;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Rules\Role;

class AddCommunityMember implements AddsCommunityMembers
{
    /**
     * Add a new member to the given community.
     */
    public function add(User $user, Community $community, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addCommunityMember', $community);

        $this->validate($community, $email, $role);

        $newCommunityMember = Jetstream::findUserByEmailOrFail($email);

        AddingCommunityMember::dispatch($community, $newCommunityMember);

        $community->users()->attach(
            $newCommunityMember, ['role' => $role]
        );

        CommunityMemberAdded::dispatch($community, $newCommunityMember);
    }

    /**
     * Validate the add member operation.
     */
    protected function validate(Community $community, string $email, ?string $role): void
    {
        Validator::make([
            'email' => $email,
            'role' => $role,
        ], $this->rules(), [
            'email.exists' => __('We were unable to find a registered user with this email address.'),
        ])->after(
            $this->ensureUserIsNotAlreadyInCommunity($community, $email)
        );
    }

    /**
     * Get the validation rules for adding a community member.
     *
     * @return array<string, Rule|array|string>
     */
    protected function rules(): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users'],
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

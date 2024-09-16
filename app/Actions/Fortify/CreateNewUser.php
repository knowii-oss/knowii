<?php

namespace App\Actions\Fortify;

use App\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    final public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), function (User $user) {
                $this->createCommunity($user);
            });
        });
    }

    /**
     * Create a personal community for the user.
     */
    final protected function createCommunity(User $user): void
    {
        $communityName = $user->name;
        if($user->name[-1] === 's') {
            $communityName .= "' Personal space";
        } else {
            $communityName .= "'s Personal space";
        }
        $communitySlug = Str::slug($user->name);

        $user->ownedCommunities()->save(Community::forceCreate([
            'user_id' => $user->id,
            'name' => $communityName,
            'slug' => $communitySlug,
            'description' => $user->name. "'s Personal space",
            'visibility' => KnowiiCommunityVisibility::Personal,
        ]));
    }
}

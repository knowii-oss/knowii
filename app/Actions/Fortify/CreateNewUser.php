<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Enums\KnowiiCommunityVisibility;
use App\Knowii;
use App\Models\Community;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<mixed>  $input
     */
    final public function create(array $input): User
    {
        // WARNING: Those rules must remain aligned with those in UpdateUserProfileInformation.php
        Validator::make($input, [
            'name' => ['required', 'string', 'max:'.Constants::$MAX_LENGTH_USER_NAME],
            'email' => ['required', 'email', 'max:'.Constants::$MAX_LENGTH_USER_EMAIL, Rule::unique('users')],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        // Derive username from the user's name
        $username = Knowii::generateRandomUsername($input['name']);

        return DB::transaction(function () use ($username, $input) {
            return tap(User::create([
                'name' => $input['name'],
                'username' => $username,
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), function (User $user) {
                // Try to recover the previous profile based on the email
                $existingProfile = UserProfile::where('email', $user->email)->first();

                if ($existingProfile) {
                    // Update the existing profile with the new user information
                    $existingProfile->update([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                    ]);
                    $user->profile()->save($existingProfile);
                } else {
                    // Create a new profile if no existing profile found
                    $user->profile()->create([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        // Add any default values for the UserProfile here
                    ]);
                }

                $this->createCommunity($user);
            });
        });
    }

    /**
     * Create a personal community for the user.
     */
    final public function createCommunity(User $user): void
    {
        $communityName = $user->name;
        if ($user->name[-1] === 's') {
            $communityName .= "' Space";
        } else {
            $communityName .= "'s Space";
        }

        $user->ownedCommunities()->save(Community::forceCreate([
            'owner_id' => $user->id,
            'name' => $communityName,
            // The slug is generated automatically
            'description' => $user->name."'s Space",
            'visibility' => KnowiiCommunityVisibility::Personal,
        ]));
    }
}

<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Knowii;
use App\KnowiiCommunityVisibility;
use App\Models\Community;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
  use PasswordValidationRules;

  /**
   * Create a newly registered user.
   *
   * @param array $input
   * @return User
   */
  final public function create(array $input): User
  {
    // WARNING: Those rules must remain aligned with those in UpdateUserProfileInformation.php
    Validator::make($input, [
      'name' => ['required', 'string', 'max:' . Constants::$MAX_LENGTH_USER_NAME],
      'email' => ['required', 'email', 'max:' . Constants::$MAX_LENGTH_USER_EMAIL, Rule::unique('users')],
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
    if ($user->name[-1] === 's') {
      $communityName .= "' Personal space";
    } else {
      $communityName .= "'s Personal space";
    }

    $user->ownedCommunities()->save(Community::forceCreate([
      'user_id' => $user->id,
      'name' => $communityName,
      // The slug is generated automatically
      'description' => $user->name . "'s Personal space",
      'visibility' => KnowiiCommunityVisibility::Personal,
    ]));
  }
}

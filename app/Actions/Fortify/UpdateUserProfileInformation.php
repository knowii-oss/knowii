<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Nette\Schema\ValidationException;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
  /**
   * Validate and update the given user's profile information.
   *
   * @param User $user
   * @param array $input
   */
  final public function update(User $user, array $input): void
  {
    // WARNING: Those rules must remain aligned with those in CreateNewUser.php
    Validator::make($input, [
      'name' => ['required', 'string', 'max:' . Constants::$MAX_LENGTH_USER_NAME],
      // WARNING: Those rules must remain aligned with those in VerifyUsernameAvailability.php and with the client-side usernameSchema
      'username' => ['string', 'min:' . Constants::$MIN_LENGTH_USER_USERNAME, 'max:' . Constants::$MAX_LENGTH_USER_USERNAME, 'regex:' . Constants::$ALLOWED_USER_USERNAME_CHARACTERS_REGEX],
      'email' => ['required', 'email', 'max:' . Constants::$MAX_LENGTH_USER_EMAIL, Rule::unique('users')->ignore($user->id)],
      'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
      'bio' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_USER_BIO],
      'location' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_USER_LOCATION],
      'phone' => ['nullable', 'string', 'max:' . Constants::$MAX_LENGTH_USER_PHONE, 'regex:' . Constants::$USER_PHONE_REGEX],
      ...array_fill_keys(Constants::$SOCIAL_MEDIA_LINK_PROPERTIES, ['nullable', 'string', 'max:255']),
    ])->validateWithBag('updateProfileInformation');

    if (isset($input['photo'])) {
      $user->updateProfilePhoto($input['photo']);
    }

    if (isset($input['bio'])) {
      $user->forceFill([
        'bio' => $input['bio'],
      ])->save();
    }

    if (isset($input['location'])) {
      $user->forceFill([
        'location' => $input['location'],
      ])->save();
    }

    if (isset($input['phone'])) {
      $user->forceFill([
        'phone' => $input['phone'],
      ])->save();
    }

    if (isset($input['username'])) {
      $this->updateUsername($user, $input['username']);
    }

    if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
      $this->updateVerifiedUser($user, $input);
    } else {
      $user->forceFill([
        'name' => $input['name'],
        'email' => $input['email'],
      ])->save();
    }

    $this->updateSocialLinks($user, $input);
  }

  /**
   * Update the given verified user's profile information.
   *
   * @param array<string, string> $input
   */
  final public function updateVerifiedUser(User $user, array $input): void
  {
    $user->forceFill([
      'name' => $input['name'],
      'email' => $input['email'],
      'email_verified_at' => null,
    ])->save();

    $user->sendEmailVerificationNotification();
  }

  /**
   * Update the username if needed/possible
   * @param User $user
   * @param string $newUsername
   * @return void
   */
  final public function updateUsername(User $user, string $newUsername): void
  {
    if (strtolower($user->username) === strtolower($newUsername)) {
      // No need to update the username if it's the same
      return;
    }

    $checker = app(VerifiesUsernameAvailability::class);
    $usernameIsAvailable = $checker->verify($user, ["usernameToCheck" => $newUsername]);

    if (!$usernameIsAvailable) {
      throw new ValidationException("The username is already taken");
    }

    $user->forceFill([
      'username' => strtolower($newUsername),
    ])->save();
  }

  /**
   * Update social links if present
   * @param User $user
   * @param array $input
   * @return void
   */
  final public function updateSocialLinks(User $user, array $input): void
  {
    $updatedLinks = [];
    foreach (Constants::$SOCIAL_MEDIA_LINK_PROPERTIES as $socialMediaLinkProperty) {
      if (isset($input[$socialMediaLinkProperty])) {
        $updatedLinks[$socialMediaLinkProperty] = $input[$socialMediaLinkProperty];
      }
    }
    $user->forceFill($updatedLinks)->save();
  }
}

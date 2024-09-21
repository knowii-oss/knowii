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
   * @param array<string, mixed> $input
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
      'social_link_x' => ['nullable', 'string', 'max: 255'],
      'social_link_website' => ['nullable', 'string', 'max: 255'],
      'social_link_newsletter' => ['nullable', 'string', 'max: 255'],
      'social_link_mastodon' => ['nullable', 'string', 'max: 255'],
      'social_link_bluesky' => ['nullable', 'string', 'max: 255'],
      'social_link_threads_dot_net' => ['nullable', 'string', 'max: 255'],
      'social_link_linkedin' => ['nullable', 'string', 'max: 255'],
      'social_link_facebook' => ['nullable', 'string', 'max: 255'],
      'social_link_instagram' => ['nullable', 'string', 'max: 255'],
      'social_link_reddit' => ['nullable', 'string', 'max: 255'],
      'social_link_medium' => ['nullable', 'string', 'max: 255'],
      'social_link_substack' => ['nullable', 'string', 'max: 255'],
      'social_link_hackernews' => ['nullable', 'string', 'max: 255'],
      'social_link_hashnode' => ['nullable', 'string', 'max: 255'],
      'social_link_dev_dot_to' => ['nullable', 'string', 'max: 255'],
      'social_link_youtube' => ['nullable', 'string', 'max: 255'],
      'social_link_tiktok' => ['nullable', 'string', 'max: 255'],
      'social_link_twitch' => ['nullable', 'string', 'max: 255'],
      'social_link_gumroad' => ['nullable', 'string', 'max: 255'],
      'social_link_buymeacoffee' => ['nullable', 'string', 'max: 255'],
      'social_link_patreon' => ['nullable', 'string', 'max: 255'],
      'social_link_producthunt' => ['nullable', 'string', 'max: 255'],
      'social_link_github' => ['nullable', 'string', 'max: 255'],
      'social_link_gitlab' => ['nullable', 'string', 'max: 255'],
    ])->validateWithBag('updateProfileInformation');

    if (isset($input['photo'])) {
      $user->updateProfilePhoto($input['photo']);
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
    $socialLinks = [
      'social_link_x',
      'social_link_website',
      'social_link_newsletter',
      'social_link_mastodon',
      'social_link_bluesky',
      'social_link_threads_dot_net',
      'social_link_linkedin',
      'social_link_facebook',
      'social_link_instagram',
      'social_link_reddit',
      'social_link_medium',
      'social_link_substack',
      'social_link_hackernews',
      'social_link_hashnode',
      'social_link_dev_dot_to',
      'social_link_youtube',
      'social_link_tiktok',
      'social_link_twitch',
      'social_link_gumroad',
      'social_link_buymeacoffee',
      'social_link_patreon',
      'social_link_producthunt',
      'social_link_github',
      'social_link_gitlab',
    ];

    $updatedLinks = [];
    foreach ($socialLinks as $link) {
      if (isset($input[$link])) {
        $updatedLinks[$link] = $input[$link];
      }
    }
    $user->forceFill($updatedLinks)->save();
  }
}

<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Exceptions\TechnicalException;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @throws TechnicalException
     * @throws ValidationException
     */
    final public function update(User $user, array $input): void
    {
        // WARNING: Those rules must remain aligned with those in CreateNewUser.php
        Validator::make($input, [
            'name' => ['required', 'string', 'max:'.Constants::$MAX_LENGTH_USER_NAME],
            // WARNING: Those rules must remain aligned with those in VerifyUsernameAvailability.php and with the client-side usernameSchema
            'username' => ['string', 'min:'.Constants::$MIN_LENGTH_USER_USERNAME, 'max:'.Constants::$MAX_LENGTH_USER_USERNAME, 'regex:'.Constants::$ALLOWED_USER_USERNAME_CHARACTERS_REGEX],
            'email' => ['required', 'email', 'max:'.Constants::$MAX_LENGTH_USER_EMAIL, Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'bio' => ['nullable', 'string', 'max:'.Constants::$MAX_LENGTH_USER_BIO],
            'location' => ['nullable', 'string', 'max:'.Constants::$MAX_LENGTH_USER_LOCATION],
            'phone' => ['nullable', 'string', 'max:'.Constants::$MAX_LENGTH_USER_PHONE, 'regex:'.Constants::$USER_PHONE_REGEX],
            ...array_fill_keys(Constants::$SOCIAL_MEDIA_LINK_PROPERTIES, ['nullable', 'string', 'max:255']),
        ])->validateWithBag('updateProfileInformation');

        $userProfile = $user->profile;
        if (! $userProfile) {
            // Should never happen. User profiles MUST be created along with user accounts
            throw new TechnicalException('User profile not found');
        }

        // WARNING: Profile information updates are NOT allowed until the user has verified their email address
        // This is important to avoid abuse of the system where users register using a mail they don't own to take over an unclaimed user profile, and try to modify it
        if (! $user->hasVerifiedEmail()) {
            return;
        }

        // Handle user account updates
        \DB::transaction(function () use ($user, $userProfile, $input) {
            if (isset($input['username'])) {
                $this->updateUsername($user, $userProfile, $input['username']);
            }

            if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
                $this->updateVerifiedUser($user, $userProfile, $input);
            } else {
                $this->updatedUnverifiedUser($user, $userProfile, $input);
            }

            // Handle name update
            if (isset($input['name'])) {
                // Update the user account
                $user->forceFill([
                    'name' => $input['name'],
                ])->save();

                // Keep the user profile in sync
                $userProfile->forceFill([
                    'name' => $input['name'],
                ])->save();
            }

            // Handle user profile updates
            if (isset($input['photo'])) {
                $userProfile->updateProfilePhoto($input['photo']);
            }

            $userProfile->forceFill([
                'bio' => $input['bio'] ?? null,
                'location' => $input['location'] ?? null,
                'phone' => $input['phone'] ?? null,
            ])->save();

            $this->updateSocialLinks($userProfile, $input);
        });
    }

    /**
     * Update the given verified user's profile information.
     */
    final public function updateVerifiedUser(User $user, UserProfile $userProfile, array $input): void
    {

        // Update the user account
        $user->forceFill([
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        // Keep the user profile in sync
        $userProfile->forceFill([
            'email' => $input['email'],
        ])->save();

        $user->sendEmailVerificationNotification();
    }

    final public function updatedUnverifiedUser(User $user, UserProfile $userProfile, array $input): void
    {
        // Update the user account
        $user->forceFill([
            'email' => $input['email'],
        ])->save();

        // Keep the user profile in sync
        $userProfile->forceFill([
            'email' => $input['email'],
        ])->save();
    }

    /**
     * Update the username if needed/possible
     */
    final public function updateUsername(User $user, UserProfile $userProfile, string $newUsername): void
    {
        if (strtolower($user->username) === strtolower($newUsername)) {
            // No need to update the username if it's the same
            return;
        }

        $checker = app(VerifiesUsernameAvailability::class);
        $usernameIsAvailable = $checker->verify($user, ['usernameToCheck' => $newUsername]);

        if (! $usernameIsAvailable) {
            throw new ValidationException('The username is already taken');
        }

        $safeNewUsername = strtolower($newUsername);

        // Update the user account
        $user->forceFill([
            'username' => $safeNewUsername,
        ])->save();

        // Keep the user profile in sync
        $userProfile->forceFill([
            'username' => $safeNewUsername,
        ])->save();
    }

    /**
     * Update social links if present
     */
    final public function updateSocialLinks(UserProfile $userProfile, array $input): void
    {
        $updatedLinks = [];
        foreach (Constants::$SOCIAL_MEDIA_LINK_PROPERTIES as $socialMediaLinkProperty) {
            if (isset($input[$socialMediaLinkProperty])) {
                $updatedLinks[$socialMediaLinkProperty] = $input[$socialMediaLinkProperty];
            } else {
                $updatedLinks[$socialMediaLinkProperty] = null;
            }
        }
        $userProfile->forceFill($updatedLinks)->save();
    }
}

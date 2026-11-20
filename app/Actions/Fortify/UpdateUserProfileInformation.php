<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Exceptions\TechnicalException;
use App\Models\User;
use App\Models\UserProfile;
use App\Rules\KnownTimeZone;
use App\Support\ProfileFieldVisibility;
use App\Support\ProfileHeadline;
use App\Support\TimeZones;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<mixed>  $input
     *
     * @throws TechnicalException
     * @throws ValidationException
     */
    final public function update(User $user, array $input): void
    {
        // The headline (#1688) is one line of plain text: it is cleaned before it is counted, here and not in the request,
        // because the action is also called without a request. A value that is not a text fails the rule below
        if (is_string($input['headline'] ?? null)) {
            $input['headline'] = ProfileHeadline::clean($input['headline']);
        }

        // WARNING: Those rules must remain aligned with those in CreateNewUser.php
        $validator = Validator::make($input, [
            'name' => ['string', 'max:'.Constants::MAX_LENGTH_USER_NAME],
            // WARNING: Those rules must remain aligned with those in VerifyUsernameAvailability.php and with the client-side usernameSchema
            'username' => ['string', 'min:'.Constants::MIN_LENGTH_USER_USERNAME, 'max:'.Constants::MAX_LENGTH_USER_USERNAME, 'regex:'.Constants::ALLOWED_USER_USERNAME_CHARACTERS_REGEX],
            'email' => ['required', 'email', 'max:'.Constants::MAX_LENGTH_USER_EMAIL, Rule::unique('users')->ignore($user->id)],
            // Only when the member sends the field (#1688): a request without it keeps the stored headline
            'headline' => ['sometimes', 'nullable', 'string', 'max:'.Constants::MAX_LENGTH_USER_HEADLINE],
            'bio' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_USER_BIO],
            'location' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_USER_LOCATION],
            'phone' => ['nullable', 'string', 'max:'.Constants::MAX_LENGTH_USER_PHONE, 'regex:'.Constants::USER_PHONE_REGEX],
            ...array_fill_keys(Constants::SOCIAL_MEDIA_LINK_PROPERTIES, ['nullable', 'string', 'max:255']),
            // The zone of the account (#1476): only when the member sends the field. An unknown name is an error
            'time_zone' => ['sometimes', 'nullable', 'string', new KnownTimeZone],
            // What the other members see (#1076, ADR 0020): known fields only, unknown keys are dropped
            'field_visibility' => ['sometimes', 'array'],
            ...array_fill_keys(array_map(fn (string $field) => 'field_visibility.'.$field, ProfileFieldVisibility::fields()), ['sometimes', 'boolean']),
        ]);

        $validator->validateWithBag('updateProfileInformation');

        /** @var array{name: string|null, username: string|null, email: string, bio: string|null, location: string|null, phone: string|null, headline?: string|null, field_visibility?: array<string, mixed>, time_zone?: string|null} $input */
        $input = $validator->validated();

        $userProfile = $user->profile;

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

            if ($input['email'] !== $user->email) {
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

            $userProfile->forceFill([
                'bio' => $input['bio'] ?? null,
                'location' => $input['location'] ?? null,
                'phone' => $input['phone'] ?? null,
                // Only a field that the member sent changes the headline (#1688): null clears it, and the column never
                // holds an empty text
                ...(array_key_exists('headline', $input) ? ['headline' => $input['headline']] : []),
            ])->save();

            $this->updateSocialLinks($userProfile, $input);

            // Only a field that the member sent changes the zone: the platform never changes it by itself (#1476)
            if (array_key_exists('time_zone', $input)) {
                $zone = $input['time_zone'];
                $user->forceFill(['time_zone' => is_string($zone) ? TimeZones::current($zone) : null])->save();
            }

            if (isset($input['field_visibility'])) {
                $userProfile->forceFill([
                    'field_visibility' => ProfileFieldVisibility::resolve([...$userProfile->fieldVisibility(), ...$input['field_visibility']]),
                ])->save();
            }
        });
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<mixed>  $input
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

    /**
     * Updated the given unverified user's profile information
     *
     * @param  array<mixed>  $input
     */
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
     *
     *
     * @throws ValidationException
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
            throw ValidationException::withMessages([
                'username' => ['The username is already taken'],
            ]);
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
     *
     * @param  array<mixed>  $input
     */
    final public function updateSocialLinks(UserProfile $userProfile, array $input): void
    {
        $updatedLinks = [];
        foreach (Constants::SOCIAL_MEDIA_LINK_PROPERTIES as $socialMediaLinkProperty) {
            if (isset($input[$socialMediaLinkProperty])) {
                $updatedLinks[$socialMediaLinkProperty] = $input[$socialMediaLinkProperty];
            } else {
                $updatedLinks[$socialMediaLinkProperty] = null;
            }
        }
        $userProfile->forceFill($updatedLinks)->save();
    }
}

<?php

namespace App\Actions\Fortify;

use App\Constants;
use App\Contracts\Users\ChangesEmailAddresses;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Exceptions\TechnicalException;
use App\Models\User;
use App\Models\UserProfile;
use App\Rules\KnownTimeZone;
use App\Support\EmailChangeBudget;
use App\Support\PasswordTries;
use App\Support\PlainText;
use App\Support\ProfileFieldVisibility;
use App\Support\TimeZones;
use App\Support\TooManyTries;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use SensitiveParameter;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * A change of the email address (#1706, ADR 0058), compared without regard to the case:
     * 1. The account keeps its address. The new address waits for its confirmation (ChangesEmailAddresses): a queued job
     *    sends the link to it, and the account has it only when the link is opened. An account whose address was never
     *    verified corrects it the same way (a correction): its other fields do not change until the address is verified.
     * 2. The answer is the same for a new address that another account has and one that no account has: the request never
     *    reads another account. The job sends the mail "This address already has an account" in place of the link.
     * 3. The current password confirms the change when the account has one.
     * 4. Each change counts in the budget of the mails of a change of the address (EmailChangeBudget), with "Send the link
     *    again", and in a budget of the day (Constants::EMAIL_CHANGES_PER_DAY).
     * The same address in another case is no change: the account keeps the stored address.
     *
     * @param  array<mixed>  $input
     *
     * @throws AuthenticationException
     * @throws TechnicalException
     * @throws ValidationException
     */
    final public function update(User $user, array $input): void
    {
        // The name, the headline (#1688) and the location are one line of plain text, and the bio is plain text of several
        // lines (ADR 0069): they are cleaned before they are counted, here and not in the request, because the action is
        // also called without a request. A value that is not a text fails the rule below
        PlainText::clean($input, ['name', 'headline', 'location'], ['bio']);

        $newAddress = is_string($input['email'] ?? null) ? trim($input['email']) : null;
        $changesAddress = $newAddress !== null && mb_strtolower($newAddress) !== mb_strtolower($user->email);
        $checksPassword = $changesAddress && $user->hasPassword();

        // Each change of the address sends a mail: one budget with "Send the link again" (#1706, ADR 0058), and a few a day
        $wait = $changesAddress ? EmailChangeBudget::waitFor($user) : 0;
        if ($wait > 0) {
            throw ValidationException::withMessages(['email' => TooManyTries::words($wait)])->errorBag('updateProfileInformation');
        }

        // WARNING: Those rules must remain aligned with those in CreateNewUser.php
        $validator = Validator::make($input, [
            'name' => ['string', 'max:'.Constants::MAX_LENGTH_USER_NAME],
            // WARNING: Those rules must remain aligned with those in VerifyUsernameAvailability.php and with the client-side usernameSchema
            'username' => ['string', 'min:'.Constants::MIN_LENGTH_USER_USERNAME, 'max:'.Constants::MAX_LENGTH_USER_USERNAME, 'regex:'.Constants::ALLOWED_USER_USERNAME_CHARACTERS_REGEX],
            // The format and the length only: whether another account has the address is for the queued job, so the answer
            // is the same for every new address (ADR 0058)
            'email' => ['required', 'email', 'max:'.Constants::MAX_LENGTH_USER_EMAIL],
            // The current password confirms a change of the address (#1706), when the account has one (an account that a
            // provider made has none). The other fields need no password. It is checked with the tries of the session
            // (App\Support\PasswordTries): a wrong one counts, and the fifth wrong one in 15 minutes ends the session
            ...($checksPassword ? ['current_password' => ['required', 'string', static function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if (! PasswordTries::check($user, static fn (): bool => is_string($value) && Hash::check($value, (string) $user->password))) {
                    $fail(__('The provided password does not match your current password.'));
                }
            }]] : []),
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

        /** @var array{name: string|null, username: string|null, email: string, current_password?: string, bio: string|null, location: string|null, phone: string|null, headline?: string|null, field_visibility?: array<string, mixed>, time_zone?: string|null} $input */
        $input = $validator->validated();

        if ($changesAddress) {
            EmailChangeBudget::count($user);
        }

        $userProfile = $user->profile;

        // WARNING: Profile information updates are NOT allowed until the user has verified their email address
        // This is important to avoid abuse of the system where users register using a mail they don't own to take over an unclaimed user profile, and try to modify it
        // The address itself is the exception (#1706): a person who typed it wrong corrects it, and the link goes to the
        // corrected address
        if (! $user->hasVerifiedEmail()) {
            if ($changesAddress) {
                $this->correctUnverifiedAddress($user, trim($input['email']), $input['current_password'] ?? null);
            }

            return;
        }

        // Handle user account updates
        \DB::transaction(function () use ($user, $userProfile, $input, $changesAddress) {
            if (isset($input['username'])) {
                $this->updateUsername($user, $userProfile, $input['username']);
            }

            // The new address waits for its confirmation (#1706, ADR 0058): the account keeps the verified address of now
            if ($changesAddress) {
                app(ChangesEmailAddresses::class)->request($user, trim($input['email']));
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
     * The correction of an address that was never verified (#1706, ADR 0058): a pending address, as a change. The link of a
     * free address confirms it, and the account then has the new address, verified; an address that another account has
     * gets the mail without a link, and the account keeps its address. The address check of the stored address and the
     * takeover by its first proof stay (the takeover clears the pending address, ADR 0059). In one transaction, on the row
     * of the account read again and locked: the password is checked against that row, and the correction waits only while
     * the row still has no verified address. A row that another proof verified in between changes nothing: no pending
     * address and no mail, and the request ends without an error. Returns whether the correction waits now; the model
     * holds the stored values after the call.
     *
     * @throws ValidationException
     */
    final public function correctUnverifiedAddress(User $user, string $address, #[SensitiveParameter] ?string $password): bool
    {
        $waits = DB::transaction(static function () use ($user, $address, $password): bool {
            // The row as it is stored now, locked until the commit
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($account->hasVerifiedEmail()) {
                return false;
            }
            if ($account->hasPassword() && (! is_string($password) || ! Hash::check($password, (string) $account->password))) {
                throw ValidationException::withMessages(['current_password' => __('The provided password does not match your current password.')])->errorBag('updateProfileInformation');
            }

            app(ChangesEmailAddresses::class)->request($account, $address);

            return true;
        });

        // The caller goes on with the stored values
        $user->refresh();

        return $waits;
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

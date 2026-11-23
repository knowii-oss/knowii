<?php

namespace App\Http\Controllers\API;

namespace App\Http\Controllers\API;

use App\Contracts\Users\ChangesEmailAddresses;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\UpdatesAvatars;
use App\Contracts\Users\UpdatesMemberListVisibility;
use App\Contracts\Users\UpdatesNotificationSettings;
use App\Contracts\Users\UpdatesOnlineStatus;
use App\Contracts\Users\UpdatesTimeZones;
use App\Contracts\Users\VerifiesUsernameAvailability;
use App\Enums\KnowiiAccountDeletionProof;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Reaction;
use App\Support\AccountDeletion;
use App\Support\Emoji;
use App\Support\SecondFactor;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;

class UserApiController extends Controller
{
    use ApiResponses;

    /**
     * The signed-in user: account fields (email, verification, two-factor status) and profile.
     */
    final public function me(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        // refresh(): every column, also the ones a model built in memory does not hold (two_factor_confirmed_at)
        return self::success('Current user', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * The emoji that the current member reacted with most, most used first, then most recent (#1204): the
     * "Frequently used" row of the reaction picker. At most 16, from the reactions of the member in every community.
     *
     * @throws AuthenticationException
     */
    final public function frequentEmoji(Request $request): JsonResponse
    {
        $user = $request->user() ?? throw new AuthenticationException;

        /** @var list<string> $emoji */
        $emoji = Reaction::query()
            ->where('user_id', $user->id)
            ->select('emoji')
            ->groupBy('emoji')
            ->orderByRaw('count(*) desc, max(created_at) desc')
            ->limit(Emoji::FREQUENT_COUNT)
            ->pluck('emoji')
            ->all();

        return self::success('Frequently used emoji', null, $emoji);
    }

    /**
     * Replace the avatar of the current user (#109): a JPEG, PNG, WebP or GIF image of 10 MB at most, re-encoded and
     * cropped to a square. Returns the current user with avatar_url.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('file', description: 'The image (multipart/form-data)', required: true, type: 'string', format: 'binary')]
    final public function updateAvatar(Request $request): JsonResponse
    {
        $user = app(UpdatesAvatars::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['file']));

        return self::success('Avatar updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Show or hide when the current user is online (#1187, ADR 0027). Hidden, the member never appears in the online
     * lists, and still sees the other members there. Returns the current user.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('show_online_status', description: 'true: the other members see when you are online. false: you never appear online', required: true, type: 'boolean', example: false)]
    final public function updateOnlineStatus(Request $request): JsonResponse
    {
        $user = app(UpdatesOnlineStatus::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['show_online_status']));

        return self::success('Online status updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Show or hide the current user in the member list of their communities (#1466). Hidden, the other members do not find
     * the member in the member list, the member search or the mention suggestions. The owner, the admins and the moderators
     * who manage members still do, and a community where the member is the owner, an admin or a moderator always lists them.
     * The member page, the posts and a typed mention do not change. Returns the current user.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('show_in_member_list', description: 'true: the other members find you in the member list of your communities. false: only the managers and you find you there, except in a community where you are on the team', required: true, type: 'boolean', example: false)]
    final public function updateMemberListVisibility(Request $request): JsonResponse
    {
        $user = app(UpdatesMemberListVisibility::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['show_in_member_list']));

        return self::success('Member list visibility updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Set the time zone of the current user (#1476, ADR 0048): an IANA name that PHP knows, like `Europe/Brussels` or `UTC`.
     * Only the member sets it, and only the member reads it. Returns the current user with `time_zone`.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('time_zone', description: 'An IANA time zone name. An older name of a zone (`Asia/Calcutta`) is accepted and stored under the current name (`Asia/Kolkata`)', required: true, type: 'string', example: 'Europe/Brussels')]
    final public function updateTimeZone(Request $request): JsonResponse
    {
        $user = app(UpdatesTimeZones::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['time_zone']));

        return self::success('Time zone updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * The account choices about notifications (#1476): `popups` turns the pop-ups of the app on or off. Returns the current
     * user with `notification_popups`.
     *
     * @throws AuthenticationException
     */
    #[BodyParameter('popups', description: 'true: the app shows pop-ups for the lines that need you. false: it does not', required: true, type: 'boolean', example: false)]
    final public function updateNotificationSettings(Request $request): JsonResponse
    {
        $user = app(UpdatesNotificationSettings::class)->update($request->user() ?? throw new AuthenticationException, $request->only(['popups']));

        return self::success('Notification settings updated', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Send the link of a change of the email address again (#1706, ADR 0058). A change of the address applies after the
     * new address is confirmed: the profile form asks for it, and the link goes to the new address. This sends a new link
     * to it; the change keeps the time of its request, and it waits the period of the privacy policy at most (7 days by
     * default). No password is needed. Shares a budget of 6 a minute with the changes of the address. Without a change
     * that waits, the answer is a validation error. Returns the current user, with `pending_email`.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    final public function resendEmailChange(Request $request): JsonResponse
    {
        $user = app(ChangesEmailAddresses::class)->resend($request->user() ?? throw new AuthenticationException);

        return self::success('Link sent again', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Cancel a change of the email address that waits for its confirmation (#1706, ADR 0058): the pending address goes,
     * and its link does not work any more. The account keeps its address. Nothing happens when no change waits. Returns
     * the current user, with `pending_email` null.
     *
     * @throws AuthenticationException
     */
    final public function cancelEmailChange(Request $request): JsonResponse
    {
        $user = app(ChangesEmailAddresses::class)->cancel($request->user() ?? throw new AuthenticationException);

        return self::success('Change of the email address cancelled', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Remove the avatar of the current user: the initials show again.
     *
     * @throws AuthenticationException
     */
    final public function deleteAvatar(Request $request): JsonResponse
    {
        $user = app(UpdatesAvatars::class)->delete($request->user() ?? throw new AuthenticationException);

        return self::success('Avatar removed', null, $user->refresh()->load('profile')->toResource());
    }

    /**
     * Delete your own account (#1492, ADR 0043). It cannot be undone. The rules of the web deletion apply: your profile, the
     * communities that you own, your memberships, reactions, tokens and sessions go, and what you wrote in the communities of
     * others stays under "Deleted member", unless you send `delete_content`. You prove the intent with your current password
     * and, when two-factor authentication is on, with a code or a recovery code (a recovery code that passes is consumed).
     *
     * An account without a password (it signs in only through the sign-in provider of the site, #1521, ADR 0052) sends no
     * password: it first confirms with a new sign-in at the provider in a browser, on the deletion section of the profile
     * settings, and then sends this request within the time that the page shows (5 minutes by default), with the code when
     * two-factor authentication is on. The confirmation is used once. Without it the answer is a business error (422) whose
     * message gives the address of that page (also in `metadata.sign_in_url`). An account without a password and without a
     * connected sign-in account gets a business error too: the operator of the site deletes it.
     *
     * Needs the ability `account:delete`, which is never in the default set of a token. A few tries a minute. Answers 204 and
     * every token of the account is gone.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    #[BodyParameter('password', description: 'Your current password: required when the account has a password, ignored otherwise', required: false, type: 'string')]
    #[BodyParameter('delete_content', description: 'Also delete your posts, comments and messages in every community (they stay under "Deleted member" otherwise)', required: false, type: 'bool')]
    #[BodyParameter('code', description: 'The code of your authenticator app or a recovery code: required when two-factor authentication is turned on', required: false, type: 'string', example: '123456')]
    final public function destroy(Request $request, StatefulGuard $guard): JsonResponse|Response
    {
        $user = $request->user() ?? throw new AuthenticationException;

        $proof = AccountDeletion::proofOf($user);
        if ($proof === KnowiiAccountDeletionProof::Unavailable) {
            Log::warning('Account deletion refused', ['reason' => 'no_way_to_prove', 'user' => $user->cuid]);

            return self::businessIssue(AccountDeletion::UNAVAILABLE_MESSAGE, ['sign_in' => [AccountDeletion::UNAVAILABLE_MESSAGE]]);
        }
        if ($proof === KnowiiAccountDeletionProof::SignIn && AccountDeletion::confirmedUntil($user) === null) {
            Log::warning('Account deletion refused', ['reason' => 'no_valid_proof', 'user' => $user->cuid]);

            return self::businessIssue(AccountDeletion::signInNeededMessage(), ['sign_in' => [AccountDeletion::signInNeededMessage()]], ['sign_in_url' => AccountDeletion::pageUrl()]);
        }

        $input = Validator::make($request->all(), [
            'password' => $proof === KnowiiAccountDeletionProof::Password ? ['required', 'string'] : ['nullable'],
            'delete_content' => ['sometimes', 'boolean'],
            'code' => ['nullable', 'string', 'max:64'],
        ])->validate();

        // The same check as the web (ConfirmPassword) and the same message
        if ($proof === KnowiiAccountDeletionProof::Password && (! is_string($input['password']) || ! app(ConfirmPassword::class)($guard, $user, $input['password']))) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        // Two-factor authentication is never skipped
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $code = $input['code'] ?? null;
            if (! is_string($code) || $code === '') {
                throw ValidationException::withMessages(['code' => __('Enter the code of your authenticator app or a recovery code.')]);
            }
            if (! SecondFactor::passesEither($user, $code)) {
                throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
            }
        }

        if ($proof === KnowiiAccountDeletionProof::Password) {
            // The model of the request, whose password was checked: the action compares its version of the sessions with the
            // row that it locks (ADR 0059)
            app(DeletesAccounts::class)->delete($user, $request->only(['delete_content']));
        } else {
            // The proof is checked again and used up in the transaction of the deletion: a second request cannot use it
            try {
                app(DeletesAccounts::class)->deleteAfterSignIn($user->fresh() ?? $user, $request->only(['delete_content']));
            } catch (BusinessException $e) {
                return self::businessIssue($e->getMessage(), $e->getErrors(), $e->getMetadata());
            }
        }

        // A call from the web app has a session: it ends with the account
        if ($request->hasSession()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return self::deleted();
    }

    /**
     * Check if the given username is available
     */
    #[BodyParameter('usernameToCheck', description: 'Username to check (3 to 36 characters)', required: true, type: 'string', example: 'ada-lovelace')]
    final public function isUsernameAvailable(Request $request): JsonResponse
    {
        Log::info('Processing API request to verify the availability of a username.');
        Log::debug('User: ', [$request->user()]);

        $checker = app(VerifiesUsernameAvailability::class);

        Log::info('Input', [$request->all()]);

        $usernameIsAvailable = false;
        $user = $request->user();

        if ($user) {
            $usernameIsAvailable = $checker->verify($user, $request->all());
        }

        return self::success('Username availability check completed', null, ['isUsernameAvailable' => $usernameIsAvailable]);
    }
}

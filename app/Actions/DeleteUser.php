<?php

namespace App\Actions;

use App\Actions\Users\UpdateAvatar;
use App\Contracts\Communities\DeletesCommunities;
use App\Contracts\Users\DeletesAccounts;
use App\Contracts\Users\DeletesMemberContent;
use App\Enums\KnowiiAccountDeletionProof;
use App\Exceptions\BusinessException;
use App\Models\CommunityInvitation;
use App\Models\InvitationImportRow;
use App\Models\User;
use App\Support\AccountDeletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Deletes an account and the personal data of the member (#1261, ADR 0024).
 *
 * What the member wrote in the communities of others stays, with "Deleted member" as its author: the author of the
 * posts and comments and the curator of the resources become null (foreign keys with nullOnDelete). The images in
 * that content stay too, without an uploader. Everything else goes: the profile, the avatar, the owned communities,
 * the memberships, the reactions, the mentions, the invitations sent to the address, the API tokens, the sessions and
 * the password reset tokens.
 *
 * With the option `delete_content`, the member also deletes their posts, comments and messages in every community
 * (#1344, ADR 0043, which changes the first point above). Resources, pages and lessons stay in any case.
 *
 * An account without a password is deleted with deleteAfterSignIn(), which uses the proof of a new sign-in at the
 * provider (#1521, ADR 0052).
 */
class DeleteUser implements DeletesAccounts, DeletesUsers
{
    /**
     * The transaction of a deletion runs again after a deadlock, at most this many times in all: a write of the same
     * account at the same time (an invitation mail of one of its communities) locks the rows in another order
     */
    private const int ATTEMPTS = 3;

    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly DeletesCommunities $deletesCommunities,
        private readonly DeletesMemberContent $deletesMemberContent,
    ) {}

    /**
     * Delete the given user: the model of the request, that the caller checked the password of. The row of the account is
     * read again and locked in the transaction of the deletion, and the account is deleted only when that row has the
     * version of the sessions of the model (ADR 0059).
     *
     * @param  array<mixed>  $input
     *
     * @throws ValidationException When the row has another version of the sessions than the model, or is gone
     */
    final public function delete(User $user, array $input = []): void
    {
        $deleteContent = self::deleteContentOf($input);

        // Only the cuid: the logs must not keep the personal data of a deleted account
        $cuid = $user->cuid;

        DB::transaction(function () use ($user, $cuid, $deleteContent) {
            // The row as it is stored now, locked until the commit. The model of the request must hold the version of the
            // sessions of that row (ADR 0059): a model that was read before a takeover deletes nothing, and the answer is
            // the one of a wrong password
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->sessionVersion() !== $user->sessionVersion()) {
                Log::warning('Account deletion refused', ['reason' => 'session_version', 'user' => $cuid]);
                throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
            }

            Log::info('Deleting user', ['user' => $cuid]);
            $this->remove($locked, $deleteContent);
        }, self::ATTEMPTS);

        $this->afterRemoval($cuid);
    }

    /**
     * Delete an account without a password with the proof of a new sign-in (#1521, ADR 0052). The row of the account is
     * locked, the proof is checked again and cleared, and the account is deleted, in one transaction: two requests at
     * the same time cannot both use it. The caller checked the two-factor code first.
     *
     * @param  array<mixed>  $input
     *
     * @throws BusinessException When the account has a password, or has no valid proof
     */
    final public function deleteAfterSignIn(User $user, array $input = []): void
    {
        $deleteContent = self::deleteContentOf($input);
        $cuid = $user->cuid;

        DB::transaction(function () use ($user, $cuid, $deleteContent) {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            if ($locked === null || AccountDeletion::proofOf($locked) !== KnowiiAccountDeletionProof::SignIn || AccountDeletion::confirmedUntil($locked) === null) {
                Log::warning('Account deletion refused', ['reason' => 'no_valid_proof', 'user' => $cuid]);
                throw new BusinessException(AccountDeletion::signInNeededMessage(), 0, null, ['sign_in' => [AccountDeletion::signInNeededMessage()]], ['sign_in_url' => AccountDeletion::pageUrl()]);
            }

            // Single use: the proof goes in the same transaction as the account
            $locked->forceFill(['deletion_signed_in_at' => null])->save();
            Log::info('Deleting user', ['user' => $cuid]);
            $this->remove($locked, $deleteContent);
        }, self::ATTEMPTS);

        $this->afterRemoval($cuid);
    }

    /**
     * @param  array<mixed>  $input
     */
    private static function deleteContentOf(array $input): bool
    {
        /** @var array{delete_content?: bool} $validated */
        $validated = Validator::make($input, ['delete_content' => ['sometimes', 'boolean']])->validate();

        return $validated['delete_content'] ?? false;
    }

    /**
     * Everything that goes with the account, in the transaction of the caller
     */
    private function remove(User $user, bool $deleteContent): void
    {
        $email = $user->email;

        // Remove the user's communities and associated elements
        $this->deleteCommunities($user);

        // What is left of the content of the member, in the communities of others (#1344, ADR 0043)
        if ($deleteContent) {
            $this->deletesMemberContent->delete($user);
        }

        // The profile holds the email, the name and every field of the member
        $user->profile()->delete();

        // An invitation stores the address in lower case, an account can hold capitals: compared without the case
        CommunityInvitation::query()->whereRaw('lower(email) = lower(?)', [$email])->delete();
        // The rows of the imports of invitations with this address (#1390); an import that the account uploaded stays, without its author
        InvitationImportRow::query()->whereRaw('lower(email) = lower(?)', [$email])->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Remove tokens
        $user->tokens()->delete();

        // Remove the user account
        $user->delete();
    }

    /**
     * Files are not part of the transaction: they go once the account is gone
     */
    private function afterRemoval(string $cuid): void
    {
        Storage::disk(UpdateAvatar::disk())->deleteDirectory('avatars/'.$cuid);

        Log::info('User deleted', ['user' => $cuid]);
    }

    /**
     * Delete the communities and community associations attached to the user.
     */
    final public function deleteCommunities(User $user): void
    {
        // Remove memberships
        $user->communities()->detach();

        // Remove owned communities
        $ownedCommunities = $user->ownedCommunities;

        foreach ($ownedCommunities as $community) {
            $this->deletesCommunities->delete($user, $community);
        }
    }
}

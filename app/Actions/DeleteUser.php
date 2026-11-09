<?php

namespace App\Actions;

use App\Actions\Users\UpdateAvatar;
use App\Contracts\Communities\DeletesCommunities;
use App\Models\CommunityInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Contracts\DeletesUsers;

/**
 * Deletes an account and the personal data of the member (#1261, ADR 0024).
 *
 * What the member wrote in the communities of others stays, with "Deleted member" as its author: the author of the
 * posts and comments and the curator of the resources become null (foreign keys with nullOnDelete). The images in
 * that content stay too, without an uploader. Everything else goes: the profile, the avatar, the owned communities,
 * the memberships, the reactions, the mentions, the invitations sent to the address, the API tokens, the sessions and
 * the password reset tokens.
 */
class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(private readonly DeletesCommunities $deletesCommunities) {}

    /**
     * Delete the given user.
     */
    final public function delete(User $user): void
    {
        // Only the cuid: the logs must not keep the personal data of a deleted account
        $cuid = $user->cuid;
        $email = $user->email;
        Log::info('Deleting user', ['user' => $cuid]);

        DB::transaction(function () use ($user, $email) {
            // Remove the user's communities and associated elements
            $this->deleteCommunities($user);

            // The profile holds the email, the name and every field of the member
            $user->profile()->delete();

            CommunityInvitation::query()->where('email', $email)->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            // Remove tokens
            $user->tokens()->delete();

            // Remove the user account
            $user->delete();
        });

        // Files are not part of the transaction: they go once the account is gone
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

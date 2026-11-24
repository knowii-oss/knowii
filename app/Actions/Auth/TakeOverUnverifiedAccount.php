<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\TakesOverUnverifiedAccounts;
use App\Contracts\Auth\UnlinksIdentities;
use App\Contracts\Realtime\EndsLiveConnections;
use App\Enums\KnowiiAddressProof;
use App\Enums\KnowiiTakeOverResult;
use App\Models\User;
use App\Support\BrowserHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * The first proof of an address takes the account over (ADR 0059, and ADR 0045 for the provider: decision of the owner,
 * 2026-11-15). The address of the account was never verified, so nobody proved before that it is theirs: a verified
 * account keeps its verified address through a change of the address (ADR 0058). The person who proves it now (at a
 * provider, with a sign-in link or with a password reset) gets the account, and everything that signed in or acted for
 * the account before goes, in one transaction, on the row of the account read again and locked:
 *
 * 1. The address is marked as verified.
 * 2. The password is removed, or replaced by the one that the person chose in a password reset.
 * 3. The remember token is replaced.
 * 4. The second factor is removed: the secret, the recovery codes and the date of the confirmation.
 * 5. The version of the sessions goes up by 1: every session of before ends at its next request, whatever the session
 *    driver (App\Http\Middleware\EndOutdatedSessions). The rows of the sessions table are deleted too.
 * 6. The API tokens and the password reset tokens of the account are deleted.
 * 7. The connections to a provider are removed, with their entitlement keys (UnlinksIdentities).
 * 8. The proof of a deletion (ADR 0052) and a pending change of the address (ADR 0058) are cleared.
 * 9. After the commit, the live connections of the account on the broadcaster end (EndsLiveConnections).
 * 10. The next page of the browser that proved the address (the page after its sign-in, or the sign-in page after a
 *     password reset) tells it to forget the pages that its history keeps (App\Support\BrowserHistory).
 *
 * The caller signs the person in after the call: a session that starts later holds the new version. The name, the
 * username, the profile and the personal community stay. The action returns what it did (KnowiiTakeOverResult), and it
 * changes nothing when the stored row has another address than the one that was proved (OtherAddress: a correction of the
 * address came in between, the proof is for an address that the account no longer has, and the caller answers as for a
 * link that does not work), or a verified address (AlreadyVerified: another proof of the same address came first, and the
 * caller goes on as for a verified account). The model of the caller holds the values of the locked row in both cases.
 * When the account was deleted after the caller read it (Gone), the action changes nothing, the model of the caller stays
 * as it is, and the caller answers as for a proof that does not work any more.
 *
 * Each write of the row checks the version of the sessions that its model read (User::setKeysForSaveQuery()): the takeover
 * writes on the locked row, so its write matches, and a model that a request read before the takeover writes nothing
 * after it.
 */
class TakeOverUnverifiedAccount implements TakesOverUnverifiedAccounts
{
    public function __construct(
        private readonly UnlinksIdentities $unlinksIdentities,
        private readonly EndsLiveConnections $endsLiveConnections,
    ) {}

    final public function takeOver(User $user, KnowiiAddressProof $proof, string $provedAddress, #[SensitiveParameter] ?string $newPassword = null): KnowiiTakeOverResult
    {
        $outcome = DB::transaction(function () use ($user, $provedAddress, $newPassword): array {
            // The row as it is stored now, locked until the commit: the action works on it, not on the model of the caller
            $account = User::query()->lockForUpdate()->find($user->id);
            if ($account === null) {
                // The account was deleted after the caller read it: there is nothing to take over
                return [KnowiiTakeOverResult::Gone, null];
            }
            if (! $account->hasAddress($provedAddress)) {
                return [KnowiiTakeOverResult::OtherAddress, $account->getAttributes()];
            }
            if (! $account->addressWasNeverVerified()) {
                return [KnowiiTakeOverResult::AlreadyVerified, $account->getAttributes()];
            }

            $account->forceFill([
                'email_verified_at' => now(),
                // The password of before cannot be used any more
                'password' => $newPassword === null ? null : Hash::make($newPassword),
                'remember_token' => Str::random(60),
                // The second factor of before goes with the password of before
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'deletion_signed_in_at' => null,
                // A change of the address that waited is not the choice of the person who proved the address
                'pending_email' => null,
                'pending_email_requested_at' => null,
                // Every session of before ends, whatever the session driver
                'session_version' => $account->sessionVersion() + 1,
            ])->save();

            DB::table('sessions')->where('user_id', $account->id)->delete();
            $account->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $account->email)->delete();

            foreach ($account->identities()->get() as $identity) {
                $this->unlinksIdentities->unlink($account, $identity);
            }

            return [KnowiiTakeOverResult::TookOver, $account->getAttributes()];
        });

        // The caller goes on with the model that it holds: it gets the values of the locked row, also when nothing was taken
        // over, so that it decides on the state that the action saw
        [$result, $stored] = $outcome;
        if ($stored === null) {
            // No row to give: the model of the caller stays as it is, and a write of it matches no row
            return $result;
        }
        $user->setRawAttributes($stored, true);
        $user->unsetRelations();
        if ($result !== KnowiiTakeOverResult::TookOver) {
            return $result;
        }

        // When the takeover is stored (a caller can hold a transaction of its own): one line of the log, with the cuid only,
        // never the address, and the end of the live connections of the account
        BrowserHistory::clear();

        $cuid = $user->cuid;
        $endsLiveConnections = $this->endsLiveConnections;
        DB::afterCommit(static function () use ($proof, $cuid, $endsLiveConnections): void {
            Log::notice($proof->takeoverLogLine(), ['user' => $cuid]);
            $endsLiveConnections->endFor($cuid);
        });

        return KnowiiTakeOverResult::TookOver;
    }
}

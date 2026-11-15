<?php

namespace App\Actions\Entitlements;

use App\Contracts\Auth\ReadsSubjectEntitlements;
use App\Contracts\Entitlements\RefreshesEntitlements;
use App\Contracts\Entitlements\SyncsEntitlementRules;
use App\Exceptions\EntitlementsReadException;
use App\Models\UserEntitlement;
use App\Models\UserIdentity;
use App\Support\OidcEntitlements;
use App\Support\OpenIdConnect;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The read of the truth (#1386, ADR 0047). Only the active keys are stored (`user_entitlements`), replaced as a whole in
 * one transaction, with the date of the check on the identity. No grant, source or date of the provider is kept, and no
 * refresh token exists.
 *
 * Two reads of the same identity never run at the same time (a lock): the answer that is written is always the one of the
 * read that finished last, and a read that waits for the lock reads again, so the truth of the later read wins.
 */
final class RefreshEntitlements implements RefreshesEntitlements
{
    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly ReadsSubjectEntitlements $reads,
        private readonly SyncsEntitlementRules $syncs,
    ) {}

    final public function refresh(UserIdentity $identity): void
    {
        if (! OidcEntitlements::enabled() || $identity->issuer !== OpenIdConnect::issuer()) {
            return;
        }

        try {
            Cache::lock('oidc:entitlements:identity:'.$identity->id, self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, fn () => $this->readAndStore($identity));
        } catch (LockTimeoutException) {
            throw new EntitlementsReadException('busy', true);
        }
    }

    private function readAndStore(UserIdentity $identity): void
    {
        $answer = $this->reads->read($identity->subject);

        $user = DB::transaction(function () use ($identity, $answer) {
            // The member may have disconnected the account while the provider was answering: nothing is stored then
            $current = UserIdentity::query()->whereKey($identity->id)->with('user')->lockForUpdate()->first();
            if ($current === null || $current->subject !== $identity->subject) {
                return null;
            }

            UserEntitlement::query()->where('user_id', $current->user_id)->where('issuer', $current->issuer)->delete();
            if (! $answer->gone && $answer->keys !== []) {
                UserEntitlement::query()->insert(array_map(
                    static fn (string $key): array => ['user_id' => $current->user_id, 'issuer' => $current->issuer, 'key' => $key],
                    $answer->keys,
                ));
            }
            $current->forceFill(['entitlements_checked_at' => $answer->gone ? null : now()])->save();

            return $current->user;
        });

        if ($user === null) {
            return;
        }

        Log::info('Entitlements read', ['user' => $user->cuid, 'issuer' => $identity->issuer, 'keys' => count($answer->keys), 'gone' => $answer->gone]);
        $this->syncs->syncMember($user);
    }
}

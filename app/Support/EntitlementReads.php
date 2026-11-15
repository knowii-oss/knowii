<?php

namespace App\Support;

use App\Jobs\ReadEntitlements;
use App\Models\AccessGroupRule;
use App\Models\Community;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Where a read of the entitlements starts, other than the change call and the nightly run (#1386, ADR 0047): a sign-in
 * through the provider, and a person who becomes a member of a community that has rules. Both only queue the job. A failure
 * to queue never breaks the sign-in or the join: the nightly read is the safety net.
 */
final class EntitlementReads
{
    /**
     * After a sign-in through the provider, once the link is made
     */
    public static function afterSignIn(string $issuer, string $subject): void
    {
        if (! OidcEntitlements::enabled() || $issuer !== OpenIdConnect::issuer()) {
            return;
        }

        $identity = UserIdentity::query()->where('issuer', $issuer)->where('subject', $subject)->first();
        if ($identity !== null) {
            self::queue($identity);
        }
    }

    /**
     * After a person became a member of a community (they joined by themselves, or accepted an invitation): a read,
     * when the community may use rules, has some, and the person connected the provider account
     */
    public static function afterJoin(User $user, Community $community): void
    {
        if (! OidcEntitlements::allows($community)) {
            return;
        }
        if (! AccessGroupRule::query()->whereIn('access_group_id', $community->accessGroups()->select('id'))->exists()) {
            return;
        }

        $identity = $user->identities()->where('issuer', OpenIdConnect::issuer() ?? '')->first();
        if ($identity !== null) {
            self::queue($identity);
        }
    }

    private static function queue(UserIdentity $identity): void
    {
        try {
            ReadEntitlements::dispatch($identity->id);
        } catch (Throwable $e) {
            Log::warning('Entitlements: a read could not be queued', ['reason' => $e::class, 'identity' => $identity->cuid]);
        }
    }
}

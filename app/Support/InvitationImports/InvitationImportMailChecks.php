<?php

namespace App\Support\InvitationImports;

use App\Contracts\Mail\ChecksMailBudgets;
use App\Enums\KnowiiInvitationImportOutcome;
use App\Exceptions\BusinessException;
use App\Models\Community;
use App\Models\User;
use App\Support\InvitationMailCaps;
use App\Support\MailSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The checks before the mails of an import go out (#1390, ADR 0061): at the confirm and at a send again. Each refuses the
 * whole set with a BusinessException (422), inside the transaction of the caller, so nothing changes. The messages never
 * show a number of the instance.
 *
 * 1. The budget holds mail now (ChecksMailBudgets::heldUntil()).
 * 2. With a monthly quota: the mails that the instance can still send this month before the hold are fewer than the set.
 *    The quota is the one of the budget: MAIL_MONTHLY_QUOTA, else the monthly limit that `mail:check-budget` read last
 *    from the provider; the use is the one that it read last, plus the mail sent since then (else `sent_count`), plus
 *    the rows of every import that wait on the queue (queued from InvitationImports::staleQueuedBefore(): an older
 *    queued row counts as not sent). Without a known quota, this check does nothing.
 * 3. The daily caps of the invitation mails of the community and of the account that sends (InvitationMailCaps).
 */
final class InvitationImportMailChecks
{
    /**
     * @throws BusinessException
     */
    public static function assertCanSend(User $user, Community $community, int $count): void
    {
        $heldUntil = app(ChecksMailBudgets::class)->heldUntil();
        if ($heldUntil !== null) {
            throw new BusinessException('This instance holds its mail now. Send the invitations from '.$heldUntil->utc()->ceilMinute()->format('Y-m-d H:i').' UTC.');
        }

        $left = self::monthLeft();
        if ($left !== null && $left < $count) {
            throw new BusinessException('This import needs '.$count.' emails. This instance cannot send so many this month. Send fewer rows.');
        }

        InvitationMailCaps::assertRoom($user, $community, $count);
    }

    /**
     * The mails that the instance can still send this month before the hold, null without a known quota
     */
    private static function monthLeft(): ?int
    {
        $now = CarbonImmutable::now('UTC');
        $month = DB::table('mail_usage_months')
            ->where('month', $now->startOfMonth()->toDateString())
            ->first(['sent_count', 'provider_limit', 'provider_used', 'provider_sent_count']);
        $row = $month === null ? [] : (array) $month;
        /** @var array<string, int|null> $numbers */
        $numbers = [];
        foreach (['sent_count', 'provider_limit', 'provider_used', 'provider_sent_count'] as $column) {
            $value = $row[$column] ?? null;
            $numbers[$column] = is_numeric($value) ? (int) $value : null;
        }
        $sent = $numbers['sent_count'] ?? 0;
        $providerLimit = $numbers['provider_limit'];

        $quota = MailSettings::monthlyQuota() ?? ($providerLimit !== null && $providerLimit > 0 ? $providerLimit : null);
        if ($quota === null) {
            return null;
        }

        $providerUsed = $numbers['provider_used'];
        $used = $providerUsed === null ? $sent : $providerUsed + max(0, $sent - ($numbers['provider_sent_count'] ?? $sent));
        $queued = DB::table('invitation_import_rows')
            ->where('outcome', KnowiiInvitationImportOutcome::Queued->value)
            ->where('queued_at', '>=', InvitationImports::staleQueuedBefore())
            ->count();

        return intdiv($quota * MailSettings::holdPercent(), 100) - $used - $queued;
    }
}

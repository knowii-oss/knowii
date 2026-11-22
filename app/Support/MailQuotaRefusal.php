<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Resend\Exceptions\ErrorException;
use Throwable;

/**
 * Reads the refusal of the mail provider for a quota in the chain of exceptions of a failed send (#1477, ADR 0054): the
 * transport throws its own exception with the error of the provider inside. Shared by the hold of the notification mail
 * and the hold of the mail of the invitations from a file (#1390, ADR 0061).
 */
final class MailQuotaRefusal
{
    public const string MONTHLY_QUOTA_EXCEEDED = 'monthly_quota_exceeded';

    public const string DAILY_QUOTA_EXCEEDED = 'daily_quota_exceeded';

    /**
     * The chain of previous exceptions is read this deep at most
     */
    private const int MAX_DEPTH = 10;

    /**
     * The error type of the provider when it refused the mail for a quota, else null. Exact text, no pattern
     *
     * @return self::MONTHLY_QUOTA_EXCEEDED|self::DAILY_QUOTA_EXCEEDED|null
     */
    public static function typeOf(Throwable $exception): ?string
    {
        $current = $exception;
        for ($depth = 0; $current !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($current instanceof ErrorException) {
                try {
                    $type = $current->getErrorType();
                } catch (Throwable) {
                    // An error of the provider without a type or a name
                    $type = null;
                }
                if ($type === self::MONTHLY_QUOTA_EXCEEDED || $type === self::DAILY_QUOTA_EXCEEDED) {
                    return $type;
                }
            }
            $current = $current->getPrevious();
        }

        return null;
    }

    /**
     * Until when a refusal holds the mail: the first day of the next month for the monthly quota, the next day (00:00 UTC)
     * for the daily quota
     */
    public static function holdEnd(string $type): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');

        return $type === self::MONTHLY_QUOTA_EXCEEDED ? $now->startOfMonth()->addMonthNoOverflow() : $now->startOfDay()->addDay();
    }
}

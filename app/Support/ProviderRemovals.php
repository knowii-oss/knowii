<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The cap of the removals of an address from the list of the mail provider (#1478, ADR 0054, groundwork 7.2 point 4): at
 * most MAIL_PROVIDER_REMOVALS_PER_HOUR an hour and MAIL_PROVIDER_REMOVALS_PER_DAY in 24 hours per address, for the mail
 * that a person asks for and for "Try this address again" together. A removal over the cap is not asked: the mail still
 * goes, and the provider skips it. The keys hold a hash of the address, never the address.
 */
final class ProviderRemovals
{
    public const string LIMITER = 'mail-provider-removals';

    private const int LOCK_SECONDS = 10;

    /**
     * @return list<Limit>
     */
    public static function limits(string $address): array
    {
        $hash = hash('sha256', mb_strtolower(trim($address)));

        return [
            Limit::perHour(MailSettings::providerRemovalsPerHour())->by('hour:'.$hash),
            Limit::perDay(MailSettings::providerRemovalsPerDay())->by('day:'.$hash),
        ];
    }

    /**
     * True, and counts one removal, when the address is under its cap. False, and counts nothing, when it is not. The check
     * and the count are one step: a lock of the address, so two mails at the same moment cannot both pass a cap of 1. A
     * lock that another request holds counts as over the cap (the mail still goes)
     */
    public static function attempt(string $address): bool
    {
        $limiter = RateLimiter::limiter(self::LIMITER);
        /** @var list<Limit> $limits */
        $limits = $limiter === null ? self::limits($address) : $limiter($address);
        $lock = Cache::lock(self::LIMITER.':lock:'.hash('sha256', mb_strtolower(trim($address))), self::LOCK_SECONDS);

        // Not acquired: another request checks the same address at this moment, and this one counts as over the cap
        $passed = $lock->get(static function () use ($limits): bool {
            foreach ($limits as $limit) {
                if (RateLimiter::tooManyAttempts(self::key($limit), $limit->maxAttempts)) {
                    return false;
                }
            }
            foreach ($limits as $limit) {
                RateLimiter::hit(self::key($limit), $limit->decaySeconds);
            }

            return true;
        });

        return $passed === true;
    }

    private static function key(Limit $limit): string
    {
        return self::LIMITER.':'.(is_string($limit->key) ? $limit->key : '');
    }
}

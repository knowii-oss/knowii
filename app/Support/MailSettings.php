<?php

namespace App\Support;

use App\Constants;

/**
 * The settings of the email delivery (#1477, ADR 0054), read from `config/mail.php` and checked in one place: the queue
 * of the notification mail, the rate limit, the monthly quota, the hold percent and the addresses of the budget alert.
 */
final class MailSettings
{
    private const int RATE_PER_SECOND_DEFAULT = 5;

    private const int HOLD_PERCENT_DEFAULT = 95;

    private const int INVITATIONS_PER_DAY_DEFAULT = 1000;

    /**
     * The queue of the notification mail: MAIL_QUEUE, `mail` when it is empty
     */
    public static function queue(): string
    {
        $queue = config('mail.queue');

        return is_string($queue) && trim($queue) !== '' ? trim($queue) : Constants::QUEUE_MAIL;
    }

    /**
     * Notification mail per second, for all mail workers together: MAIL_RATE_LIMIT_PER_SECOND, default 5 (also for an
     * empty or a wrong value, so that a mistake never removes the limit). 0 means no limit; a value below 0 counts as 0
     */
    public static function ratePerSecond(): int
    {
        return max(0, self::integer('mail.rate_limit_per_second', self::RATE_PER_SECOND_DEFAULT));
    }

    /**
     * MAIL_MONTHLY_QUOTA, or null when it is empty or 0
     */
    public static function monthlyQuota(): ?int
    {
        $quota = self::integer('mail.budget.monthly_quota', 0);

        return $quota > 0 ? $quota : null;
    }

    /**
     * Notification mail stops at this share of the quota: MAIL_NOTIFICATION_HOLD_PERCENT, default 95 (also for an empty
     * or a wrong value), kept between 1 and 100 (100: no reserve)
     */
    public static function holdPercent(): int
    {
        return max(1, min(100, self::integer('mail.budget.hold_percent', self::HOLD_PERCENT_DEFAULT)));
    }

    /**
     * The invitation mails that one community sends in 24 hours: MAIL_INVITATIONS_PER_COMMUNITY_PER_DAY, at least 1,
     * default 1000 (also for an empty or a wrong value)
     */
    public static function invitationsPerCommunityPerDay(): int
    {
        $cap = self::integer('mail.invitations.per_community_per_day', self::INVITATIONS_PER_DAY_DEFAULT);

        return $cap >= 1 ? $cap : self::INVITATIONS_PER_DAY_DEFAULT;
    }

    /**
     * The invitation mails that one account sends in 24 hours, over all its communities:
     * MAIL_INVITATIONS_PER_ACCOUNT_PER_DAY, at least 1, default 1000 (also for an empty or a wrong value)
     */
    public static function invitationsPerAccountPerDay(): int
    {
        $cap = self::integer('mail.invitations.per_account_per_day', self::INVITATIONS_PER_DAY_DEFAULT);

        return $cap >= 1 ? $cap : self::INVITATIONS_PER_DAY_DEFAULT;
    }

    /**
     * The invitation mails that the whole instance sends in 24 hours, over all its communities and accounts (#1713):
     * MAIL_INVITATIONS_PER_INSTANCE_PER_DAY, at least 1, default 1000 (also for an empty or a wrong value)
     */
    public static function invitationsPerInstancePerDay(): int
    {
        $cap = self::integer('mail.invitations.per_instance_per_day', self::INVITATIONS_PER_DAY_DEFAULT);

        return $cap >= 1 ? $cap : self::INVITATIONS_PER_DAY_DEFAULT;
    }

    /**
     * The removals of an address from the list of the provider in one hour (#1478): MAIL_PROVIDER_REMOVALS_PER_HOUR, at
     * least 1, default 1 (also for an empty or a wrong value)
     */
    public static function providerRemovalsPerHour(): int
    {
        $cap = self::integer('mail.provider_removals.per_hour', 1);

        return $cap >= 1 ? $cap : 1;
    }

    /**
     * The removals of an address from the list of the provider in 24 hours (#1478): MAIL_PROVIDER_REMOVALS_PER_DAY, at
     * least 1, default 3 (also for an empty or a wrong value)
     */
    public static function providerRemovalsPerDay(): int
    {
        $cap = self::integer('mail.provider_removals.per_day', 3);

        return $cap >= 1 ? $cap : 3;
    }

    /**
     * The addresses of MAIL_ALERT_TO, as they are configured (the check skips the invalid ones)
     *
     * @return list<string>
     */
    public static function alertTo(): array
    {
        $list = config('mail.budget.alert_to');
        if (is_string($list)) {
            $list = explode(',', $list);
        }
        if (! is_array($list)) {
            return [];
        }

        $addresses = [];
        foreach ($list as $address) {
            if (is_string($address) && trim($address) !== '') {
                $addresses[] = trim($address);
            }
        }

        return $addresses;
    }

    /**
     * The usage of the provider can be read: the mailer is Resend and its key is set
     */
    public static function readsProviderUsage(): bool
    {
        return config('mail.default') === 'resend' && self::resendKey() !== null;
    }

    /**
     * The mail goes through Resend: the provider with a list of suppressed addresses (#1478). With `smtp` or `log`, the
     * platform never calls the provider
     */
    public static function sendsThroughResend(): bool
    {
        return config('mail.default') === 'resend';
    }

    public static function resendKey(): ?string
    {
        $key = config('services.resend.key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * A whole number of the configuration (an environment value can be a numeric string), else the default
     */
    private static function integer(string $key, int $default): int
    {
        $value = config($key);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $default;
    }
}

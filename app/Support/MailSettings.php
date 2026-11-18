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

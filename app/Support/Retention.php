<?php

namespace App\Support;

use App\Actions\Auth\MagicLinks;
use App\Constants;
use App\Models\CommunityInvitation;

/**
 * The retention periods in one place (ADR 0030): the scheduled prune tasks and the privacy policy read them here, so
 * the policy never promises a period that the code does not apply.
 */
final class Retention
{
    public static function sessionMinutes(): int
    {
        return self::int('session.lifetime', 120);
    }

    public static function signInLinkMinutes(): int
    {
        return MagicLinks::VALIDITY_MINUTES;
    }

    public static function passwordResetMinutes(): int
    {
        return self::int('auth.passwords.users.expire', 60);
    }

    public static function invitationValidityDays(): int
    {
        return CommunityInvitation::VALIDITY_DAYS;
    }

    public static function expiredInvitationsDays(): int
    {
        return self::int('retention.expired_invitations_days', 30);
    }

    public static function apiTokenDefaultDays(): int
    {
        return (int) Constants::API_TOKEN_DEFAULT_EXPIRY;
    }

    public static function expiredApiTokensDays(): int
    {
        return self::int('retention.expired_api_tokens_days', 30);
    }

    public static function failedJobsDays(): int
    {
        return self::int('retention.failed_jobs_days', 7);
    }

    public static function unusedUploadsDays(): int
    {
        return self::int('retention.unused_uploads_days', 7);
    }

    public static function logDays(): int
    {
        return self::int('logging.channels.daily.days', 14);
    }

    public static function waitlistMonths(): int
    {
        return self::int('retention.waitlist_months', 12);
    }

    public static function inactiveContactsDays(): int
    {
        return self::int('retention.inactive_contacts_days', 30);
    }

    /**
     * @return array{daily: int, weekly: int, monthly: int}
     */
    public static function backups(): array
    {
        return [
            'daily' => self::int('retention.backups.daily', 7),
            'weekly' => self::int('retention.backups.weekly', 4),
            'monthly' => self::int('retention.backups.monthly', 12),
        ];
    }

    /**
     * The periods as words, for the placeholders of the legal pages: "{{retention.session}}" becomes "2 hours".
     *
     * @return array<string, string>
     */
    public static function periods(): array
    {
        $backups = self::backups();

        return [
            'session' => self::minutes(self::sessionMinutes()),
            'sign_in_link' => self::minutes(self::signInLinkMinutes()),
            'password_reset_link' => self::minutes(self::passwordResetMinutes()),
            'invitation' => self::count(self::invitationValidityDays(), 'day'),
            'expired_invitations' => self::count(self::expiredInvitationsDays(), 'day'),
            'api_token_default' => self::count(self::apiTokenDefaultDays(), 'day'),
            'expired_api_tokens' => self::count(self::expiredApiTokensDays(), 'day'),
            'failed_jobs' => self::count(self::failedJobsDays(), 'day'),
            'unused_uploads' => self::count(self::unusedUploadsDays(), 'day'),
            'logs' => self::count(self::logDays(), 'day'),
            'waitlist' => self::count(self::waitlistMonths(), 'month'),
            'inactive_contacts' => self::count(self::inactiveContactsDays(), 'day'),
            'backups_daily' => (string) $backups['daily'],
            'backups_weekly' => (string) $backups['weekly'],
            'backups_monthly' => (string) $backups['monthly'],
            'backups_longest' => self::count($backups['monthly'], 'month'),
        ];
    }

    private static function minutes(int $minutes): string
    {
        return $minutes >= 120 && $minutes % 60 === 0 ? self::count(intdiv($minutes, 60), 'hour') : self::count($minutes, 'minute');
    }

    private static function count(int $value, string $unit): string
    {
        return $value.' '.$unit.($value === 1 ? '' : 's');
    }

    private static function int(string $key, int $default): int
    {
        $value = config($key, $default);

        return max(1, is_numeric($value) ? (int) $value : $default);
    }
}

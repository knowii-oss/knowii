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

    /**
     * An import of invitations from a file that nobody sent (#1390)
     */
    public static function invitationImportPreviewHours(): int
    {
        return self::int('retention.invitation_import_previews_hours', 24);
    }

    /**
     * The record of a sent import lives as long as an invitation: the mail tells the person that the address is deleted
     * that long after the invitation ends (#1390)
     */
    public static function invitationImportsDays(): int
    {
        return self::invitationValidityDays() + self::expiredInvitationsDays();
    }

    /**
     * A row of `invitation_mails` (the community, the account and the time of an invitation mail) counts in the daily caps
     * for InvitationMailCaps::WINDOW_HOURS, and the daily prune step deletes it after that: it stays this many days at
     * most (#1390)
     */
    public static function invitationMailsDays(): int
    {
        return intdiv(InvitationMailCaps::WINDOW_HOURS, 24) + 1;
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

    public static function exportsDays(): int
    {
        return self::int('retention.exports_days', 7);
    }

    public static function notificationsReadDays(): int
    {
        return self::int('retention.notifications_read_days', 60);
    }

    public static function notificationsUnreadDays(): int
    {
        return self::int('retention.notifications_unread_days', 90);
    }

    public static function eventAnswersDays(): int
    {
        return self::int('retention.event_answers_days', 90);
    }

    /**
     * The record of a join through an invite link stays this many days after the join (#1391, ADR 0062)
     */
    public static function inviteLinkUsesDays(): int
    {
        return self::int('retention.invite_link_uses_days', 365);
    }

    /**
     * A new email address waits for its confirmation this many days at most (#1706, ADR 0058)
     */
    public static function pendingEmailDays(): int
    {
        return self::int('retention.pending_email_days', 7);
    }

    /**
     * A row of the delivery log (#1478)
     */
    public static function mailDeliveriesDays(): int
    {
        return self::int('retention.mail_deliveries_days', 30);
    }

    /**
     * The id of a report of the mail provider (#1478)
     */
    public static function mailWebhookEventsDays(): int
    {
        return self::int('retention.mail_webhook_events_days', 30);
    }

    /**
     * A count of soft bounces that stopped nothing, after the last bounce (#1478)
     */
    public static function emailSoftBouncesDays(): int
    {
        return self::int('retention.email_soft_bounces_days', 90);
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
            'invitation_import_previews' => self::count(self::invitationImportPreviewHours(), 'hour'),
            'invitation_imports' => self::count(self::invitationImportsDays(), 'day'),
            'invitation_mails' => self::count(self::invitationMailsDays(), 'day').' at most',
            'api_token_default' => self::count(self::apiTokenDefaultDays(), 'day'),
            'expired_api_tokens' => self::count(self::expiredApiTokensDays(), 'day'),
            'failed_jobs' => self::count(self::failedJobsDays(), 'day'),
            'unused_uploads' => self::count(self::unusedUploadsDays(), 'day'),
            'notifications_read' => self::count(self::notificationsReadDays(), 'day'),
            'notifications_unread' => self::count(self::notificationsUnreadDays(), 'day'),
            'event_answers' => self::count(self::eventAnswersDays(), 'day'),
            'invite_link_uses' => self::count(self::inviteLinkUsesDays(), 'day'),
            'pending_email' => self::count(self::pendingEmailDays(), 'day'),
            'mail_deliveries' => self::count(self::mailDeliveriesDays(), 'day'),
            'mail_webhook_events' => self::count(self::mailWebhookEventsDays(), 'day'),
            'email_soft_bounces' => self::count(self::emailSoftBouncesDays(), 'day'),
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

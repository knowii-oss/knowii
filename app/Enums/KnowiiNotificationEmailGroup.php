<?php

namespace App\Enums;

/**
 * The notification email groups (#1478, ADR 0054, design 5.6 point 8): what "Stop emails like this" stops, from the link
 * of a notification email and from its `List-Unsubscribe` header. The member knows a group, not a kind of notification.
 * Account mail (the sign-in link, the address check, the invitations) has no group and no stop link.
 */
enum KnowiiNotificationEmailGroup: string
{
    case RepliesAndMentions = 'replies_and_mentions';
    case DirectMessages = 'direct_messages';
    case ImportantNews = 'important_news';
    case WeeklySummary = 'weekly_summary';
    case Events = 'events';

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

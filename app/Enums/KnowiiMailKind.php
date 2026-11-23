<?php

namespace App\Enums;

/**
 * What a sent mail is, on its row of the delivery log (#1478, ADR 0054): the group of a notification mail, an invitation, a
 * mail about the account, a mail that the person asked for a moment ago (the sign-in link, the address check, the password
 * reset, the confirmation of a new address), or the alert of the operator. App\Support\MailKinds maps each mail class to it.
 */
enum KnowiiMailKind: string
{
    case RepliesAndMentions = 'replies_and_mentions';
    case DirectMessages = 'direct_messages';
    case ImportantNews = 'important_news';
    case WeeklySummary = 'weekly_summary';
    case Events = 'events';
    case Invitation = 'invitation';
    case Account = 'account';
    case AskedFor = 'asked_for';
    case Operator = 'operator';

    public static function ofGroup(KnowiiNotificationEmailGroup $group): self
    {
        return self::from($group->value);
    }

    /**
     * @return list<string>
     */
    public static function toStringArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}

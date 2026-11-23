<?php

namespace App\Support;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Models\NotificationEmailChoice;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * The stop link of a notification email (#1478, ADR 0054, owner decision 7): a permanent signed address of `mail.stop.show`
 * with the cuid of the account and the group, never a stored token. One builder for the `List-Unsubscribe` header and the
 * footer link, so they cannot differ. The signature covers the path and the query, not the method: the same address
 * serves the GET of the page and the one-click POST (RFC 8058). It is signed with `APP_KEY` (`APP_PREVIOUS_KEYS` keeps the
 * old links valid after a change of the key). A notification mail builds it when it is sent, never in its queued job
 * (ADR 0064 point 11).
 */
final class MailStopLink
{
    public const string RESULT_STOPPED = 'stopped';

    public const string RESULT_UNDONE = 'undone';

    public static function for(User $user, KnowiiNotificationEmailGroup $group): string
    {
        return self::address($user->cuid, $group);
    }

    /**
     * The same address from the cuid of an account (the page reads it from the link)
     */
    public static function address(string $userCuid, KnowiiNotificationEmailGroup $group): string
    {
        return URL::signedRoute('mail.stop.show', self::parameters($userCuid, $group));
    }

    /**
     * The page after a stop of the page: the done state with "Undo"
     */
    public static function done(string $userCuid, KnowiiNotificationEmailGroup $group): string
    {
        return URL::signedRoute('mail.stop.show', self::parameters($userCuid, $group) + ['result' => self::RESULT_STOPPED]);
    }

    /**
     * The page after the undo
     */
    public static function undone(string $userCuid, KnowiiNotificationEmailGroup $group): string
    {
        return URL::signedRoute('mail.stop.show', self::parameters($userCuid, $group) + ['result' => self::RESULT_UNDONE]);
    }

    /**
     * The undo: the state before the stop is part of the signed address, so no state is stored for it and nobody can
     * invent another one. In slice 4b it is always the default of the group
     */
    public static function undo(string $userCuid, KnowiiNotificationEmailGroup $group): string
    {
        return URL::signedRoute('mail.stop.undo', self::parameters($userCuid, $group) + ['restore' => NotificationEmailChoice::STATE_DEFAULT]);
    }

    /**
     * @return array{user: string, group: string}
     */
    private static function parameters(string $userCuid, KnowiiNotificationEmailGroup $group): array
    {
        return ['user' => $userCuid, 'group' => $group->value];
    }
}

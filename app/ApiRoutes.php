<?php

namespace App;

/**
 * Centralized definition of the server-side API paths.
 *
 * The relative path constants (e.g. {@see self::COMMUNITY}) are used directly in
 * routes/api.php, inside the {@see self::VERSION} prefix group. Tests (and any
 * other consumer that needs a full, client-facing URL) build paths with
 * {@see self::path()}, which prefixes {@see self::BASE_PATH} and substitutes the
 * route parameters.
 *
 * Goal: avoid hardcoded API path strings across the codebase.
 */
final class ApiRoutes
{
    /**
     * The API version prefix. The outer `api` prefix is added by Laravel's
     * routing configuration (see bootstrap/app.php).
     */
    public const VERSION = 'v1';

    /**
     * The full base path as seen by clients and tests, e.g. `api/v1`.
     */
    public const BASE_PATH = 'api/'.self::VERSION;

    // Route parameter names (must match the route-model-binding variables used
    // by the API controllers).
    public const PARAM_COMMUNITY = 'community';

    public const PARAM_SPACE_GROUP = 'spaceGroup';

    public const PARAM_SPACE = 'space';

    public const PARAM_POST = 'post';

    public const PARAM_PAGE = 'page';

    public const PARAM_MESSAGE = 'message';

    // Direct messages (#1186): named after Community::conversations() for the scoped binding
    public const PARAM_CONVERSATION = 'conversation';

    public const PARAM_USER_CUID = 'userCuid';

    public const PARAM_COMMENT = 'comment';

    // Named after Space::resources() for the scoped binding
    public const PARAM_RESOURCE = 'resource';

    public const PARAM_RESOURCE_COMMENT = 'comment';

    // Course materials (#1336): named after Space::materials() for the scoped binding
    public const PARAM_MATERIAL = 'material';

    // Events of an events space (#1513): named after Space::events() for the scoped binding
    public const PARAM_EVENT = 'event';

    // The user cuid of a member (#1242)
    public const PARAM_MEMBER = 'member';

    // Named after Community::communityInvitations() for the scoped binding
    public const PARAM_INVITATION = 'communityInvitation';

    // Named after Community::accessGroups() for the scoped binding (#1241)
    public const PARAM_ACCESS_GROUP = 'accessGroup';

    // Named after the route parameter of the notification routes
    public const PARAM_NOTIFICATION = 'notification';

    // The cuid of a connected provider account, in the path of the identity routes (#1385)
    public const PARAM_IDENTITY_CUID = 'identityCuid';

    // Paths relative to the version prefix, used in routes/api.php.
    public const USER = 'user';

    // The avatar of the current user (#109)
    public const USER_AVATAR = 'user/avatar';

    // Show or hide when the current user is online (#1187)
    public const USER_ONLINE_STATUS = 'user/online-status';

    // The time zone and the notification choices of the current user (#1476, ADR 0048)
    public const USER_TIME_ZONE = 'user/time-zone';

    public const USER_NOTIFICATION_SETTINGS = 'user/notification-settings';

    // The members the current user blocked (#1186)
    public const USER_BLOCKS = 'user/blocks';

    // The emoji that the current member reacts with most (#1204)
    public const USER_FREQUENT_EMOJI = 'user/frequent-emoji';

    public const USER_BLOCK = self::USER_BLOCKS.'/{'.self::PARAM_USER_CUID.'}';

    // The OpenID Connect provider accounts that the current user connected (#1385, ADR 0045)
    public const USER_IDENTITIES = 'user/identities';

    public const USER_IDENTITY = self::USER_IDENTITIES.'/{'.self::PARAM_IDENTITY_CUID.'}';

    // A change of the email address that waits for its confirmation (#1706, ADR 0058): cancel it, or send its link again
    public const USER_EMAIL_CHANGE = 'user/email-change';

    public const USER_EMAIL_CHANGE_RESEND = 'user/email-change/resend';

    // The entitlement keys of the current user at the sign-in provider (#1386, ADR 0047)
    public const USER_ENTITLEMENTS = 'user/entitlements';

    // "My events" (#1515, ADR 0053): the events that the current member answered Yes or Replay only, and the ones they host
    public const USER_EVENTS = 'user/events';

    public const AUTH_LOGIN = 'auth/login';

    public const PING = 'ping';

    public const NEWSLETTER = 'newsletter';

    public const USERS_IS_USERNAME_AVAILABLE = 'users/is-username-available';

    public const COMMUNITIES = 'communities';

    public const COMMUNITY = self::COMMUNITIES.'/{'.self::PARAM_COMMUNITY.'}';

    // Whether a shared community can take a name (#1136)
    public const COMMUNITIES_IS_NAME_AVAILABLE = self::COMMUNITIES.'/is-name-available';

    // The rights of the moderators (#1240)
    public const COMMUNITY_MODERATOR_PERMISSIONS = self::COMMUNITY.'/moderator-permissions';

    // Who can join the community (#1486): by invitation or open
    public const COMMUNITY_JOIN_POLICY = self::COMMUNITY.'/join-policy';

    // Search engines may index the public spaces of the community (#1487)
    public const COMMUNITY_INDEXING = self::COMMUNITY.'/indexing';

    // The owner gives the community to an admin (#1339)
    public const COMMUNITY_OWNERSHIP = self::COMMUNITY.'/ownership';

    // The newest posts of the public discussion spaces, for the public page of the community (#1485)
    public const COMMUNITY_NEWEST_POSTS = self::COMMUNITY.'/newest-posts';

    public const COMMUNITY_SPACE_GROUPS = self::COMMUNITY.'/space-groups';

    public const COMMUNITY_SPACE_GROUPS_REORDER = self::COMMUNITY_SPACE_GROUPS.'/reorder';

    public const COMMUNITY_SPACE_GROUP = self::COMMUNITY_SPACE_GROUPS.'/{'.self::PARAM_SPACE_GROUP.'}';

    public const COMMUNITY_SPACE_GROUP_SPACES_REORDER = self::COMMUNITY_SPACE_GROUP.'/spaces/reorder';

    public const COMMUNITY_SPACES = self::COMMUNITY.'/spaces';

    public const COMMUNITY_SPACE = self::COMMUNITY_SPACES.'/{'.self::PARAM_SPACE.'}';

    // Resources of a resources space (ADR 0015)
    public const COMMUNITY_SPACE_RESOURCES = self::COMMUNITY_SPACE.'/resources';

    public const COMMUNITY_SPACE_RESOURCE = self::COMMUNITY_SPACE_RESOURCES.'/{'.self::PARAM_RESOURCE.'}';

    public const COMMUNITY_SPACE_RESOURCE_COMMENTS = self::COMMUNITY_SPACE_RESOURCE.'/comments';

    public const COMMUNITY_SPACE_RESOURCE_CAPTURE = self::COMMUNITY_SPACE_RESOURCE.'/capture';

    public const COMMUNITY_SPACE_RESOURCE_COMMENT = self::COMMUNITY_SPACE_RESOURCE_COMMENTS.'/{'.self::PARAM_RESOURCE_COMMENT.'}';

    public const COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS = self::COMMUNITY_SPACE_RESOURCE_COMMENT.'/reactions';

    public const COMMUNITY_SPACE_POSTS = self::COMMUNITY_SPACE.'/posts';

    // Events of events spaces (#1513, ADR 0053)
    public const COMMUNITY_SPACE_EVENTS = self::COMMUNITY_SPACE.'/events';

    public const COMMUNITY_SPACE_EVENT = self::COMMUNITY_SPACE_EVENTS.'/{'.self::PARAM_EVENT.'}';

    public const COMMUNITY_SPACE_EVENT_STATE = self::COMMUNITY_SPACE_EVENT.'/state';

    public const COMMUNITY_SPACE_EVENT_DUPLICATE = self::COMMUNITY_SPACE_EVENT.'/duplicate';

    // The answer of the member to an event, and the answers that the host and the team read (#1515, ADR 0053)
    public const COMMUNITY_SPACE_EVENT_ANSWER = self::COMMUNITY_SPACE_EVENT.'/answer';

    public const COMMUNITY_SPACE_EVENT_ANSWERS = self::COMMUNITY_SPACE_EVENT.'/answers';

    // The calendar file of an event, for a member (#1515, ADR 0053): one iCalendar file, built at each request
    public const COMMUNITY_SPACE_EVENT_CALENDAR_FILE = self::COMMUNITY_SPACE_EVENT.'/calendar-file';

    // The upcoming events of every events space that the reader can read (#1513)
    public const COMMUNITY_EVENTS = self::COMMUNITY.'/events';

    // Messages of chat spaces (#1185)
    public const COMMUNITY_SPACE_MESSAGES = self::COMMUNITY_SPACE.'/messages';

    public const COMMUNITY_SPACE_MESSAGES_READ = self::COMMUNITY_SPACE_MESSAGES.'/read';

    public const COMMUNITY_SPACE_MESSAGE = self::COMMUNITY_SPACE_MESSAGES.'/{'.self::PARAM_MESSAGE.'}';

    public const COMMUNITY_SPACE_MESSAGE_THREAD = self::COMMUNITY_SPACE_MESSAGE.'/thread';

    public const COMMUNITY_SPACE_MESSAGE_REACTIONS = self::COMMUNITY_SPACE_MESSAGE.'/reactions';

    // The follow state of the current member for a chat thread: read, follow, stop following, mute, and the undo of the mute (#1475, #1509, ADR 0042)
    public const COMMUNITY_SPACE_MESSAGE_FOLLOW = self::COMMUNITY_SPACE_MESSAGE.'/follow';

    public const COMMUNITY_SPACE_MESSAGE_MUTE = self::COMMUNITY_SPACE_MESSAGE.'/mute';

    // Pages of page spaces (#1179)
    public const COMMUNITY_SPACE_PAGES = self::COMMUNITY_SPACE.'/pages';

    public const COMMUNITY_SPACE_PAGES_ORDER = self::COMMUNITY_SPACE_PAGES.'/order';

    public const COMMUNITY_SPACE_PAGE = self::COMMUNITY_SPACE_PAGES.'/{'.self::PARAM_PAGE.'}';

    // Course progress of the current member (#1304)
    public const COMMUNITY_SPACE_PROGRESS = self::COMMUNITY_SPACE.'/progress';

    public const COMMUNITY_SPACE_PAGE_PROGRESS = self::COMMUNITY_SPACE_PAGE.'/progress';

    // The quiz of a lesson (#1335, ADR 0038)
    public const COMMUNITY_SPACE_PAGE_QUIZ = self::COMMUNITY_SPACE_PAGE.'/quiz';

    public const COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS = self::COMMUNITY_SPACE_PAGE_QUIZ.'/attempts';

    public const COMMUNITY_SPACE_POST = self::COMMUNITY_SPACE_POSTS.'/{'.self::PARAM_POST.'}';

    public const COMMUNITY_SPACE_POST_PIN = self::COMMUNITY_SPACE_POST.'/pin';

    // The follow state of the current member for a post thread: read, follow, stop following, mute, and the undo of the mute (#1475, #1509, ADR 0042)
    public const COMMUNITY_SPACE_POST_FOLLOW = self::COMMUNITY_SPACE_POST.'/follow';

    public const COMMUNITY_SPACE_POST_MUTE = self::COMMUNITY_SPACE_POST.'/mute';

    public const COMMUNITY_SPACE_POST_COMMENTS = self::COMMUNITY_SPACE_POST.'/comments';

    public const COMMUNITY_SPACE_POST_COMMENT = self::COMMUNITY_SPACE_POST_COMMENTS.'/{'.self::PARAM_COMMENT.'}';

    public const COMMUNITY_SPACE_POST_REACTIONS = self::COMMUNITY_SPACE_POST.'/reactions';

    public const COMMUNITY_SPACE_POST_COMMENT_REACTIONS = self::COMMUNITY_SPACE_POST_COMMENT.'/reactions';

    // Supplementary materials of course spaces (#1336, ADR 0037)
    public const COMMUNITY_SPACE_MATERIALS = self::COMMUNITY_SPACE.'/materials';

    public const COMMUNITY_SPACE_MATERIALS_ORDER = self::COMMUNITY_SPACE_MATERIALS.'/order';

    public const COMMUNITY_SPACE_MATERIAL = self::COMMUNITY_SPACE_MATERIALS.'/{'.self::PARAM_MATERIAL.'}';

    public const COMMUNITY_SPACE_MATERIAL_DOWNLOAD = self::COMMUNITY_SPACE_MATERIAL.'/download';

    public const MARKDOWN_PREVIEW = 'markdown/preview';

    public const COMMUNITY_MEMBERS = self::COMMUNITY.'/members';

    // Member management (#1242)
    public const COMMUNITY_MEMBER = self::COMMUNITY_MEMBERS.'/{'.self::PARAM_MEMBER.'}';

    // The membership of the current user: they leave the community with a DELETE (#1338), and join an open community with
    // a POST (#1486)
    public const COMMUNITY_MEMBERSHIP = self::COMMUNITY.'/membership';

    // The members whom a manager removed, who cannot join again by themselves, and the action that lifts it (#1486)
    public const COMMUNITY_JOIN_BLOCKS = self::COMMUNITY.'/join-blocks';

    public const COMMUNITY_JOIN_BLOCK = self::COMMUNITY_JOIN_BLOCKS.'/{'.self::PARAM_MEMBER.'}';

    public const COMMUNITY_INVITATIONS = self::COMMUNITY.'/invitations';

    public const COMMUNITY_INVITATION = self::COMMUNITY_INVITATIONS.'/{'.self::PARAM_INVITATION.'}';

    public const COMMUNITY_UPLOADS = self::COMMUNITY.'/uploads';

    // Access groups (#1241, ADR 0025)
    public const COMMUNITY_ACCESS_GROUPS = self::COMMUNITY.'/access-groups';

    public const COMMUNITY_ACCESS_GROUP = self::COMMUNITY_ACCESS_GROUPS.'/{'.self::PARAM_ACCESS_GROUP.'}';

    public const COMMUNITY_ACCESS_GROUP_MEMBERS = self::COMMUNITY_ACCESS_GROUP.'/members';

    public const COMMUNITY_ACCESS_GROUP_MEMBER = self::COMMUNITY_ACCESS_GROUP_MEMBERS.'/{'.self::PARAM_MEMBER.'}';

    // Access groups that open whole space groups (#1422, ADR 0041)
    public const COMMUNITY_ACCESS_GROUP_SPACE_GROUPS = self::COMMUNITY_ACCESS_GROUP.'/space-groups';

    public const COMMUNITY_SPACE_GROUP_ACCESS_GROUPS = self::COMMUNITY_SPACE_GROUP.'/access-groups';

    // The entitlement keys that give an access group (#1386, ADR 0047)
    public const COMMUNITY_ACCESS_GROUP_RULES = self::COMMUNITY_ACCESS_GROUP.'/rules';

    // Direct messages (#1186, ADR 0028)
    public const COMMUNITY_CONVERSATIONS = self::COMMUNITY.'/conversations';

    public const COMMUNITY_CONVERSATION = self::COMMUNITY_CONVERSATIONS.'/{'.self::PARAM_CONVERSATION.'}';

    public const COMMUNITY_CONVERSATION_MESSAGES = self::COMMUNITY_CONVERSATION.'/messages';

    public const COMMUNITY_CONVERSATION_READ = self::COMMUNITY_CONVERSATION.'/read';

    public const COMMUNITY_CONVERSATION_MESSAGE = self::COMMUNITY_CONVERSATION_MESSAGES.'/{'.self::PARAM_MESSAGE.'}';

    public const COMMUNITY_CONVERSATION_MESSAGE_REACTIONS = self::COMMUNITY_CONVERSATION_MESSAGE.'/reactions';

    // Notifications of the current member, in every community (#1474, ADR 0042)
    public const NOTIFICATIONS = 'notifications';

    public const NOTIFICATIONS_COUNT = self::NOTIFICATIONS.'/count';

    public const NOTIFICATIONS_SEEN = self::NOTIFICATIONS.'/seen';

    // "I am caught up" (#1475): the open rows of "Following" are read
    public const NOTIFICATIONS_CAUGHT_UP = self::NOTIFICATIONS.'/caught-up';

    public const NOTIFICATION = self::NOTIFICATIONS.'/{'.self::PARAM_NOTIFICATION.'}';

    public const NOTIFICATION_READ = self::NOTIFICATION.'/read';

    public const NOTIFICATION_UNREAD = self::NOTIFICATION.'/unread';

    // The level that the current member chose for each space of a community, and for one space (#1475, ADR 0042)
    public const COMMUNITY_NOTIFICATION_LEVELS = self::COMMUNITY.'/notification-levels';

    public const COMMUNITY_SPACE_NOTIFICATION_LEVEL = self::COMMUNITY_SPACE.'/notification-level';

    // The data of the "Catch up" card, and the visit of a space (#1476, ADR 0048)
    public const COMMUNITY_CATCH_UP = self::COMMUNITY.'/catch-up';

    public const COMMUNITY_SPACE_VISIT = self::COMMUNITY_SPACE.'/visit';

    // Who is online now (#1187, ADR 0027)
    public const COMMUNITY_ONLINE_MEMBERS = self::COMMUNITY.'/online-members';

    public const COMMUNITY_SPACE_ONLINE_MEMBERS = self::COMMUNITY_SPACE.'/online-members';

    /**
     * Build a full, client-facing API path from a relative path, substituting
     * any route parameters.
     *
     * Example: ApiRoutes::path(ApiRoutes::COMMUNITY, [ApiRoutes::PARAM_COMMUNITY => $cuid])
     *          => "api/v1/communities/<cuid>"
     *
     * @param  array<string, string|int>  $params  Route parameter values, keyed by parameter name.
     */
    public static function path(string $relativePath, array $params = []): string
    {
        $path = self::BASE_PATH.'/'.$relativePath;

        foreach ($params as $name => $value) {
            $path = str_replace('{'.$name.'}', (string) $value, $path);
        }

        return $path;
    }
}

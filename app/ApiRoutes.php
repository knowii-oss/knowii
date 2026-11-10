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

    // The user cuid of a member (#1242)
    public const PARAM_MEMBER = 'member';

    // Named after Community::communityInvitations() for the scoped binding
    public const PARAM_INVITATION = 'communityInvitation';

    // Named after Community::accessGroups() for the scoped binding (#1241)
    public const PARAM_ACCESS_GROUP = 'accessGroup';

    // Paths relative to the version prefix, used in routes/api.php.
    public const USER = 'user';

    // The avatar of the current user (#109)
    public const USER_AVATAR = 'user/avatar';

    // Show or hide when the current user is online (#1187)
    public const USER_ONLINE_STATUS = 'user/online-status';

    // The members the current user blocked (#1186)
    public const USER_BLOCKS = 'user/blocks';

    public const USER_BLOCK = self::USER_BLOCKS.'/{'.self::PARAM_USER_CUID.'}';

    public const AUTH_LOGIN = 'auth/login';

    public const PING = 'ping';

    public const WAITLIST = 'waitlist';

    public const NEWSLETTER = 'newsletter';

    public const USERS_IS_USERNAME_AVAILABLE = 'users/is-username-available';

    public const COMMUNITIES = 'communities';

    public const COMMUNITY = self::COMMUNITIES.'/{'.self::PARAM_COMMUNITY.'}';

    // The rights of the moderators (#1240)
    public const COMMUNITY_MODERATOR_PERMISSIONS = self::COMMUNITY.'/moderator-permissions';

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

    // Messages of chat spaces (#1185)
    public const COMMUNITY_SPACE_MESSAGES = self::COMMUNITY_SPACE.'/messages';

    public const COMMUNITY_SPACE_MESSAGES_READ = self::COMMUNITY_SPACE_MESSAGES.'/read';

    public const COMMUNITY_SPACE_MESSAGE = self::COMMUNITY_SPACE_MESSAGES.'/{'.self::PARAM_MESSAGE.'}';

    public const COMMUNITY_SPACE_MESSAGE_THREAD = self::COMMUNITY_SPACE_MESSAGE.'/thread';

    public const COMMUNITY_SPACE_MESSAGE_REACTIONS = self::COMMUNITY_SPACE_MESSAGE.'/reactions';

    // Pages of page spaces (#1179)
    public const COMMUNITY_SPACE_PAGES = self::COMMUNITY_SPACE.'/pages';

    public const COMMUNITY_SPACE_PAGES_ORDER = self::COMMUNITY_SPACE_PAGES.'/order';

    public const COMMUNITY_SPACE_PAGE = self::COMMUNITY_SPACE_PAGES.'/{'.self::PARAM_PAGE.'}';

    public const COMMUNITY_SPACE_POST = self::COMMUNITY_SPACE_POSTS.'/{'.self::PARAM_POST.'}';

    public const COMMUNITY_SPACE_POST_PIN = self::COMMUNITY_SPACE_POST.'/pin';

    public const COMMUNITY_SPACE_POST_COMMENTS = self::COMMUNITY_SPACE_POST.'/comments';

    public const COMMUNITY_SPACE_POST_COMMENT = self::COMMUNITY_SPACE_POST_COMMENTS.'/{'.self::PARAM_COMMENT.'}';

    public const COMMUNITY_SPACE_POST_REACTIONS = self::COMMUNITY_SPACE_POST.'/reactions';

    public const COMMUNITY_SPACE_POST_COMMENT_REACTIONS = self::COMMUNITY_SPACE_POST_COMMENT.'/reactions';

    public const MARKDOWN_PREVIEW = 'markdown/preview';

    public const COMMUNITY_MEMBERS = self::COMMUNITY.'/members';

    // Member management (#1242)
    public const COMMUNITY_MEMBER = self::COMMUNITY_MEMBERS.'/{'.self::PARAM_MEMBER.'}';

    public const COMMUNITY_INVITATIONS = self::COMMUNITY.'/invitations';

    public const COMMUNITY_INVITATION = self::COMMUNITY_INVITATIONS.'/{'.self::PARAM_INVITATION.'}';

    public const COMMUNITY_UPLOADS = self::COMMUNITY.'/uploads';

    // Access groups (#1241, ADR 0025)
    public const COMMUNITY_ACCESS_GROUPS = self::COMMUNITY.'/access-groups';

    public const COMMUNITY_ACCESS_GROUP = self::COMMUNITY_ACCESS_GROUPS.'/{'.self::PARAM_ACCESS_GROUP.'}';

    public const COMMUNITY_ACCESS_GROUP_MEMBERS = self::COMMUNITY_ACCESS_GROUP.'/members';

    public const COMMUNITY_ACCESS_GROUP_MEMBER = self::COMMUNITY_ACCESS_GROUP_MEMBERS.'/{'.self::PARAM_MEMBER.'}';

    // Direct messages (#1186, ADR 0028)
    public const COMMUNITY_CONVERSATIONS = self::COMMUNITY.'/conversations';

    public const COMMUNITY_CONVERSATION = self::COMMUNITY_CONVERSATIONS.'/{'.self::PARAM_CONVERSATION.'}';

    public const COMMUNITY_CONVERSATION_MESSAGES = self::COMMUNITY_CONVERSATION.'/messages';

    public const COMMUNITY_CONVERSATION_READ = self::COMMUNITY_CONVERSATION.'/read';

    public const COMMUNITY_CONVERSATION_MESSAGE = self::COMMUNITY_CONVERSATION_MESSAGES.'/{'.self::PARAM_MESSAGE.'}';

    public const COMMUNITY_CONVERSATION_MESSAGE_REACTIONS = self::COMMUNITY_CONVERSATION_MESSAGE.'/reactions';

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

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

    public const PARAM_COMMUNITY_RESOURCE_COLLECTION = 'communityResourceCollection';

    public const PARAM_SPACE_GROUP = 'spaceGroup';

    public const PARAM_SPACE = 'space';

    public const PARAM_POST = 'post';

    public const PARAM_COMMENT = 'comment';

    // Paths relative to the version prefix, used in routes/api.php.
    public const USER = 'user';

    public const AUTH_LOGIN = 'auth/login';

    public const PING = 'ping';

    public const WAITLIST = 'waitlist';

    public const USERS_IS_USERNAME_AVAILABLE = 'users/is-username-available';

    public const COMMUNITIES = 'communities';

    public const COMMUNITY = self::COMMUNITIES.'/{'.self::PARAM_COMMUNITY.'}';

    public const COMMUNITY_RESOURCE_COLLECTIONS = self::COMMUNITY.'/resource-collections';

    public const COMMUNITY_RESOURCE_COLLECTION = self::COMMUNITY_RESOURCE_COLLECTIONS.'/{'.self::PARAM_COMMUNITY_RESOURCE_COLLECTION.'}';

    public const COMMUNITY_RESOURCE_TEXT_ARTICLES = self::COMMUNITY_RESOURCE_COLLECTION.'/text-articles';

    public const COMMUNITY_SPACE_GROUPS = self::COMMUNITY.'/space-groups';

    public const COMMUNITY_SPACE_GROUPS_REORDER = self::COMMUNITY_SPACE_GROUPS.'/reorder';

    public const COMMUNITY_SPACE_GROUP = self::COMMUNITY_SPACE_GROUPS.'/{'.self::PARAM_SPACE_GROUP.'}';

    public const COMMUNITY_SPACE_GROUP_SPACES_REORDER = self::COMMUNITY_SPACE_GROUP.'/spaces/reorder';

    public const COMMUNITY_SPACES = self::COMMUNITY.'/spaces';

    public const COMMUNITY_SPACE = self::COMMUNITY_SPACES.'/{'.self::PARAM_SPACE.'}';

    public const COMMUNITY_SPACE_POSTS = self::COMMUNITY_SPACE.'/posts';

    public const COMMUNITY_SPACE_POST = self::COMMUNITY_SPACE_POSTS.'/{'.self::PARAM_POST.'}';

    public const COMMUNITY_SPACE_POST_PIN = self::COMMUNITY_SPACE_POST.'/pin';

    public const COMMUNITY_SPACE_POST_COMMENTS = self::COMMUNITY_SPACE_POST.'/comments';

    public const COMMUNITY_SPACE_POST_COMMENT = self::COMMUNITY_SPACE_POST_COMMENTS.'/{'.self::PARAM_COMMENT.'}';

    public const COMMUNITY_SPACE_POST_REACTIONS = self::COMMUNITY_SPACE_POST.'/reactions';

    public const COMMUNITY_SPACE_POST_COMMENT_REACTIONS = self::COMMUNITY_SPACE_POST_COMMENT.'/reactions';

    public const MARKDOWN_PREVIEW = 'markdown/preview';

    public const COMMUNITY_MEMBERS = self::COMMUNITY.'/members';

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

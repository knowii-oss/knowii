<?php

namespace App;

class Constants
{
    public const int MIN_LENGTH_USER_USERNAME = 3;

    public const int MAX_LENGTH_USER_USERNAME = 36;

    public const string ALLOWED_USER_USERNAME_INPUT_CHARACTERS = 'a-zA-Z-\s '; // Used to allow providing user name as input to derive the username. There, spaces are allowed

    public const string ALLOWED_USER_USERNAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-]+$/';

    public const int MAX_LENGTH_USER_NAME = 255;

    public const int MAX_LENGTH_USER_EMAIL = 255;

    public const int MAX_LENGTH_WAITLIST_EMAIL = 255;

    // Name of the hidden field that only bots fill in. WARNING: aligned with WAITLIST_HONEYPOT_FIELD (libs/common constants.ts)
    public const string WAITLIST_HONEYPOT_FIELD = 'website';

    // The sign-up form has the same kind of hidden field (#737)
    public const string SIGNUP_HONEYPOT_FIELD = 'website';

    // Waitlist requests allowed per minute and per IP address
    public const int WAITLIST_MAX_REQUESTS_PER_MINUTE = 5;

    // Cloudflare documents a maximum length of 2048 characters for Turnstile tokens
    public const int MAX_LENGTH_TURNSTILE_TOKEN = 2048;

    public const int MAX_LENGTH_USER_BIO = 512;

    public const int MAX_LENGTH_USER_LOCATION = 128;

    public const int MAX_LENGTH_USER_PHONE = 48;

    public const string USER_PHONE_REGEX = '/^[0-9\-+().\/ ]+$/';

    public const int MAX_LENGTH_SLUG = 128;

    public const int MIN_LENGTH_COMMUNITY_NAME = 3;

    public const int MAX_LENGTH_COMMUNITY_NAME = 64;

    public const int MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;

    public const string ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@\' ]+$/';

    public const int MIN_LENGTH_COMMUNITY_RESOURCE_NAME = 3;

    public const int MAX_LENGTH_COMMUNITY_RESOURCE_NAME = 64;

    public const string ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX = '/^[a-zA-Z0-9-@,\' ]+$/';

    public const int MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION = 255;

    // Space groups and spaces (#1173). WARNING: aligned with constants.ts (libs/common)
    public const int MIN_LENGTH_SPACE_GROUP_NAME = 2;

    public const int MAX_LENGTH_SPACE_GROUP_NAME = 64;

    public const int MIN_LENGTH_SPACE_NAME = 2;

    public const int MAX_LENGTH_SPACE_NAME = 64;

    public const int MAX_LENGTH_SPACE_DESCRIPTION = 255;

    public const int MAX_LENGTH_SPACE_EMOJI = 16;

    // Limits the size of a community navigation (Circle allows 40 spaces on our plan)
    public const int MAX_SPACE_GROUPS_PER_COMMUNITY = 50;

    public const int MAX_SPACES_PER_COMMUNITY = 200;

    // WARNING: aligned with MAX_LENGTH_SPACE_URL (constants.ts)
    public const int MAX_LENGTH_SPACE_URL = 2048;

    // Posts (#1174). WARNING: aligned with constants.ts (libs/common)
    public const int MIN_LENGTH_POST_TITLE = 3;

    public const int MAX_LENGTH_POST_TITLE = 200;

    public const int MAX_LENGTH_POST_BODY = 50000;

    // Chat messages (#1185). WARNING: aligned with constants.ts
    public const int MAX_LENGTH_CHAT_MESSAGE = 10000;

    public const int CHAT_MESSAGES_PER_PAGE = 50;

    public const int CHAT_MESSAGES_PER_MINUTE = 30;

    public const int MAX_LENGTH_COMMENT_BODY = 10000;

    // Comments created per minute and per user
    public const int COMMENTS_PER_MINUTE = 10;

    // Reaction toggles per minute and per user
    public const int REACTIONS_PER_MINUTE = 60;

    // Image uploads (#1178). WARNING: aligned with constants.ts (libs/common)
    public const int MAX_UPLOAD_SIZE_KILOBYTES = 10240;

    public const int MAX_UPLOAD_IMAGE_PIXELS = 40_000_000;

    // Longest side after the resize
    public const int MAX_UPLOAD_IMAGE_DIMENSION = 2000;

    // Avatars (#109): square, this many pixels on each side
    public const int AVATAR_SIZE = 256;

    public const int UPLOAD_IMAGE_QUALITY = 82;

    // Smaller versions of an uploaded image (#1207, ADR 0040): widths in pixels, in increasing order
    public const int UPLOAD_IMAGE_SIZE_SMALL = 640;

    public const int UPLOAD_IMAGE_SIZE_MEDIUM = 1280;

    public const array UPLOAD_IMAGE_SIZES = [self::UPLOAD_IMAGE_SIZE_SMALL, self::UPLOAD_IMAGE_SIZE_MEDIUM];

    // Largest page or image that the server downloads from an external address (#1231)
    public const int MAX_FETCH_SIZE_BYTES = 10 * 1024 * 1024;

    // Capture of resources (ADR 0018): a plain HTTP fetch in a queued job
    public const int CAPTURE_TIMEOUT_SECONDS = 15;

    public const string CAPTURE_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Knowii/1.0 (+https://knowii.net)';

    // Images of a captured article stored by Knowii (ADR 0018): the first ones, the others become their alternative text
    public const int CAPTURE_MAX_IMAGES = 20;

    // Manual captures ("Capture again") per member and per minute
    public const int CAPTURES_PER_MINUTE = 6;

    // Stored resource images (#1063): largest side in pixels
    public const int RESOURCE_COVER_MAX_DIMENSION = 800;

    public const int RESOURCE_FAVICON_MAX_DIMENSION = 64;

    public const int MAX_LENGTH_UPLOAD_ALT = 500;

    // Images in one post, comment, message or page (#1207). WARNING: aligned with constants.ts
    public const int MAX_IMAGES_PER_CONTENT = 20;

    // Course materials (#1336). WARNING: aligned with constants.ts
    public const int MIN_LENGTH_COURSE_MATERIAL_TITLE = 1;

    public const int MAX_LENGTH_COURSE_MATERIAL_TITLE = 120;

    // For the whole course, for one section or for one lesson
    public const int MAX_COURSE_MATERIALS_PER_TARGET = 20;

    // Lesson quizzes (#1335, ADR 0038). WARNING: aligned with constants.ts
    public const int MIN_QUIZ_QUESTIONS = 1;

    public const int MAX_QUIZ_QUESTIONS = 50;

    public const int MIN_QUIZ_CHOICES = 2;

    public const int MAX_QUIZ_CHOICES = 8;

    public const int MAX_LENGTH_QUIZ_PROMPT = 2000;

    public const int MAX_LENGTH_QUIZ_EXPLANATION = 2000;

    public const int MAX_LENGTH_QUIZ_CHOICE = 300;

    // Percent of correct answers needed to pass, when the manager gives none
    public const int DEFAULT_QUIZ_PASS_MARK = 70;

    // Quiz attempts per minute and per user: trying again at once is normal, guessing in a loop is not
    public const int QUIZ_ATTEMPTS_PER_MINUTE = 20;

    public const int UPLOADS_PER_MINUTE = 20;

    public const int INVITATIONS_PER_MINUTE = 10;

    // Access groups (#1241, ADR 0025). WARNING: aligned with access-group.schema.ts
    public const int MIN_LENGTH_ACCESS_GROUP_NAME = 2;

    public const int MAX_LENGTH_ACCESS_GROUP_NAME = 64;

    public const int MAX_LENGTH_ACCESS_GROUP_DESCRIPTION = 500;

    public const int MAX_ACCESS_GROUPS_PER_COMMUNITY = 50;

    // Lifetimes of a personal API token, in days, or "never" (#1267, ADR 0022)
    // WARNING: aligned with API_TOKEN_EXPIRY_CHOICES (jetstream-inertia.intf.ts)
    public const array API_TOKEN_EXPIRY_CHOICES = ['30', '90', '365', 'never'];

    public const string API_TOKEN_DEFAULT_EXPIRY = '90';

    // Community name checks (#1136) per minute and per user: the forms send one after each pause in the typing
    public const int NAME_CHECKS_PER_MINUTE = 60;

    // Joins of an open community (#1486) per minute and per user
    public const int JOINS_PER_MINUTE = 10;

    // Ownership transfers (#1339) per minute and per user: each one checks the password of the owner
    public const int OWNERSHIP_TRANSFERS_PER_MINUTE = 5;

    // Markdown preview requests (editor) per minute and per user
    public const int MARKDOWN_PREVIEWS_PER_MINUTE = 60;

    // Posts created per minute and per user
    public const int POSTS_PER_MINUTE = 5;

    // Starts and ends of a sign-in with the OpenID Connect provider per minute and per IP address (#1385)
    public const int OIDC_REQUESTS_PER_MINUTE = 10;

    // Calls of the sign-in provider to the entitlements webhook per minute and per IP address (#1386, ADR 0047). The provider
    // joins the changes of one person into one call, and retries with a growing delay
    public const int OIDC_ENTITLEMENT_EVENTS_PER_MINUTE = 120;

    // An entitlement key (free text of a rule, or a key that the provider sends): letters, digits, dot, dash and underscore
    public const int MAX_LENGTH_ENTITLEMENT_KEY = 100;

    public const string ENTITLEMENT_KEY_PATTERN = '/^[A-Za-z0-9._-]+$/';

    // At most this many keys are kept for a person, and this many keys are given to an access group (#1386)
    public const int MAX_ENTITLEMENTS_PER_USER = 200;

    public const int MAX_ENTITLEMENT_RULES_PER_GROUP = 20;

    // Reads of the public spaces per minute and per IP address, for visitors (#1387, ADR 0044). Signed-in requests are not limited here
    public const int PUBLIC_READS_PER_MINUTE = 60;

    // The public page of a community shows this many of the newest posts of its public discussion spaces, with an excerpt of this length (#1485)
    public const int PUBLIC_NEWEST_POSTS = 10;

    public const int PUBLIC_POST_EXCERPT_LENGTH = 200;

    // Pagination (ADR 0011: limit and offset, defaults of the NBB guide)
    public const int PAGINATION_DEFAULT_LIMIT = 10;

    public const int PAGINATION_MAX_LIMIT = 100;

    // Notifications (#1474, ADR 0042): the queues of the worker (it reads broadcasts, notifications, default, in this order)
    public const string QUEUE_BROADCASTS = 'broadcasts';

    public const string QUEUE_NOTIFICATIONS = 'notifications';

    // A page of the list of notifications
    public const int NOTIFICATIONS_PER_PAGE = 20;

    // The list folds a row into "Older" and the count forgets it after this many days without news
    public const int NOTIFICATIONS_FOLD_DAYS = 14;

    // Storm cap: a member gets at most this many unread mention rows from the same author in the window
    public const int NOTIFICATIONS_MENTION_CAP = 3;

    public const int NOTIFICATIONS_MENTION_CAP_MINUTES = 60;

    // An item older than this at the moment of the event gives no notification (imports that forgot to be silent)
    public const int NOTIFICATIONS_ITEM_MAX_AGE_MINUTES = 15;

    public const int NOTIFICATIONS_EXCERPT_LENGTH = 300;

    // The authors that a bundled row names, the others are counted
    public const int NOTIFICATIONS_MAX_ACTORS = 3;

    // Rows that one request marks as seen
    public const int NOTIFICATIONS_SEEN_MAX = 100;

    // A new post waits this long before its fan-out to the members at "Everything": edits and deletions inside the wait tell nobody (#1475, design 6.11)
    public const int NOTIFICATIONS_POST_FANOUT_DELAY_MINUTES = 5;

    // The fan-out reads its recipients in chunks of this size, one queued job per chunk (#1475, design 6.14)
    public const int NOTIFICATIONS_FANOUT_CHUNK = 500;

    /**
     * @var list<string>
     */
    public const array SOCIAL_MEDIA_LINK_PROPERTIES = [
        'social_link_x',
        'social_link_website',
        'social_link_newsletter',
        'social_link_mastodon',
        'social_link_bluesky',
        'social_link_threads_dot_net',
        'social_link_linkedin',
        'social_link_facebook',
        'social_link_instagram',
        'social_link_reddit',
        'social_link_medium',
        'social_link_substack',
        'social_link_hackernews',
        'social_link_hashnode',
        'social_link_dev_dot_to',
        'social_link_youtube',
        'social_link_tiktok',
        'social_link_twitch',
        'social_link_gumroad',
        'social_link_buymeacoffee',
        'social_link_patreon',
        'social_link_producthunt',
        'social_link_github',
        'social_link_gitlab',
        'social_link_wikipedia',
        'social_link_google_scholar',
        'social_link_orcid',
        'social_link_kofi',
    ];

    // WebSockets
    public const string WS_CHANNEL_COMMUNITIES = 'communities';

    public const string WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID = '{communityCuid}';

    public const string WS_CHANNEL_COMMUNITY = 'community.{communityCuid}';

    public const string WS_CHANNEL_COMMUNITY_SPACE_PARAM_COMMUNITY_CUID = '{communityCuid}';

    public const string WS_CHANNEL_COMMUNITY_SPACE_PARAM_SPACE_CUID = '{spaceCuid}';

    public const string WS_CHANNEL_COMMUNITY_SPACE = 'community.{communityCuid}.space.{spaceCuid}';

    // Presence channels (#1187, ADR 0027). WARNING: aligned with use-presence.ts
    public const string WS_CHANNEL_COMMUNITY_ONLINE = 'community.{communityCuid}.online';

    public const string WS_CHANNEL_COMMUNITY_SPACE_PRESENCE = 'community.{communityCuid}.space.{spaceCuid}.presence';

    // Direct messages (#1186, ADR 0028). WARNING: aligned with use-socket.ts and use-presence.ts
    public const string WS_CHANNEL_CONVERSATION_PARAM_CONVERSATION_CUID = '{conversationCuid}';

    public const string WS_CHANNEL_CONVERSATION = 'conversation.{conversationCuid}';

    public const string WS_CHANNEL_CONVERSATION_PRESENCE = 'conversation.{conversationCuid}.presence';

    // The events of one member (their conversations changed)
    public const string WS_CHANNEL_USER_PARAM_USER_CUID = '{userCuid}';

    public const string WS_CHANNEL_USER = 'user.{userCuid}';

    // At most this many members in an online list (API and pages)
    public const int MAX_ONLINE_MEMBERS_LISTED = 100;
}

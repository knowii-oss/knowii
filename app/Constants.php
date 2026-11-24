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

    // Requests of the newsletter form allowed per minute and per IP address (the waitlist form that also used it is gone, #1574)
    public const int WAITLIST_MAX_REQUESTS_PER_MINUTE = 5;

    // Cloudflare documents a maximum length of 2048 characters for Turnstile tokens
    public const int MAX_LENGTH_TURNSTILE_TOKEN = 2048;

    // One line under the name of the member (#1688). WARNING: aligned with MAX_LENGTH_USER_HEADLINE (constants.ts)
    public const int MAX_LENGTH_USER_HEADLINE = 80;

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

    // Limits the size of a community navigation
    public const int MAX_SPACE_GROUPS_PER_COMMUNITY = 50;

    public const int MAX_SPACES_PER_COMMUNITY = 200;

    // WARNING: aligned with MAX_LENGTH_SPACE_URL (constants.ts)
    public const int MAX_LENGTH_SPACE_URL = 2048;

    // Posts (#1174). WARNING: aligned with constants.ts (libs/common)
    public const int MIN_LENGTH_POST_TITLE = 3;

    public const int MAX_LENGTH_POST_TITLE = 200;

    public const int MAX_LENGTH_POST_BODY = 50000;

    // Events of an events space (#1513, ADR 0053). WARNING: aligned with constants.ts (libs/common)
    public const int MIN_LENGTH_EVENT_TITLE = 3;

    public const int MAX_LENGTH_EVENT_TITLE = 200;

    public const int MAX_LENGTH_EVENT_BODY = 20000;

    public const int MAX_LENGTH_EVENT_MEETING_URL = 2048;

    public const int MAX_LENGTH_EVENT_PLACE = 255;

    public const int MAX_LENGTH_EVENT_COVER_ALT = 125;

    // An event ends at most this many hours after its start
    public const int MAX_EVENT_DURATION_HOURS = 24;

    // After the event (#1516): the replay link and the notes. WARNING: aligned with constants.ts (libs/common)
    public const int MAX_LENGTH_EVENT_REPLAY_URL = 2048;

    public const int MAX_LENGTH_EVENT_NOTES = 20000;

    // The limit of places of an event (#1515): it counts the Yes answers. The migration holds the same numbers in a check
    public const int MIN_EVENT_CAPACITY = 1;

    public const int MAX_EVENT_CAPACITY = 5000;

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

    // Redirects followed by one fetch: each one is checked and pinned like the first address
    public const int CAPTURE_MAX_REDIRECTS = 5;

    // Time to open the connection of one fetch
    public const int CAPTURE_CONNECT_TIMEOUT_SECONDS = 5;

    // Time for all the images of one capture together: the cover, the icon and the images of the article
    public const int CAPTURE_IMAGES_SECONDS = 45;

    // Time of one capture job: the page, the images, the parse. Below the retry_after of the queue (90 seconds)
    public const int CAPTURE_JOB_TIMEOUT_SECONDS = 80;

    // Longest captured article that is stored, in characters: a longer one is cut at the last paragraph before it
    public const int MAX_CAPTURE_MARKDOWN_LENGTH = 200_000;

    // The agent names the project, not one instance (#1653)
    public const string CAPTURE_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Knowii/1.0 (+https://github.com/knowii-oss/knowii)';

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

    // Adds and removals of a member of an access group per minute and per user, together: the numbers of the invitations
    public const int ACCESS_GROUP_MEMBER_CHANGES_PER_MINUTE = self::INVITATIONS_PER_MINUTE;

    // Adds and removals of a member tag on a member per minute and per user, together
    public const int MEMBER_TAG_MEMBER_CHANGES_PER_MINUTE = self::INVITATIONS_PER_MINUTE;

    // Requests of the password reset form per hour and per IP address, for any address: one person never reaches it
    public const int PASSWORD_RESETS_PER_IP_PER_HOUR = 20;

    // Requests of the sign-in link form per hour and per IP address, for any address: one person never reaches it
    public const int SIGN_IN_LINKS_PER_IP_PER_HOUR = 20;

    // Requests of the sign-up form per hour and per IP address, for any address: one person never reaches it
    public const int SIGN_UPS_PER_IP_PER_HOUR = 20;

    // Requests of the page of the link of a sign-up per minute and per IP address: the numbers of a sign-in link
    public const int SIGN_UP_LINKS_PER_MINUTE = 5;

    // Requests of the page of a password reset (`POST /reset-password`) per minute and per IP address: the numbers of the
    // page of the link of a sign-up
    public const int PASSWORD_UPDATES_PER_MINUTE = 5;

    // Sign-ins with a password (the web and the API) per minute and per IP address alone, for any addresses, beside the
    // limit per address and IP address
    public const int SIGN_INS_PER_IP_PER_MINUTE = 30;

    // Account mails of each kind per recipient address and per day (App\Support\AccountMailCaps): the sign-in link, the
    // sign-up mail, the password reset mail, the mail of a change of the address
    public const int ACCOUNT_MAILS_PER_ADDRESS_PER_DAY = 10;

    // After the cap of the day, one more account mail of the same kind goes to the address at most once in these hours
    public const int ACCOUNT_MAILS_OVER_THE_CAP_GAP_HOURS = 3;

    // A password reset link is replaced by a new one only after these seconds: a request before sends no mail, and the link
    // of the first mail keeps working
    public const int PASSWORD_RESET_LINK_SECONDS = 300;

    // The largest page of the pending invitations (#1390): a file adds up to 1000 of them. WARNING: aligned with constants.ts
    public const int MAX_INVITATIONS_PAGE_LIMIT = 1000;

    // Invitations from a CSV file (#1390, ADR 0061). WARNING: aligned with constants.ts
    // The data rows of one file, its size, its columns and the characters of one cell
    public const int MAX_INVITATION_IMPORT_ROWS = 1000;

    public const int MAX_INVITATION_IMPORT_KILOBYTES = 1024;

    public const int MAX_INVITATION_IMPORT_COLUMNS = 20;

    public const int MAX_INVITATION_IMPORT_CELL_LENGTH = 1000;

    // "Invite again the people who did not join": this many days after the confirm at the earliest
    public const int INVITATION_IMPORT_RESEND_AFTER_DAYS = 3;

    // Uploads, confirms and sends again per minute and per user
    public const int INVITATION_IMPORTS_PER_MINUTE = 5;

    // Access groups (#1241, ADR 0025). WARNING: aligned with access-group.schema.ts
    public const int MIN_LENGTH_ACCESS_GROUP_NAME = 2;

    public const int MAX_LENGTH_ACCESS_GROUP_NAME = 64;

    public const int MAX_LENGTH_ACCESS_GROUP_DESCRIPTION = 500;

    public const int MAX_ACCESS_GROUPS_PER_COMMUNITY = 50;

    // Member tags (#1389, ADR 0060). WARNING: aligned with constants.ts. The name column holds 32 characters
    public const int MIN_LENGTH_MEMBER_TAG_NAME = 2;

    public const int MAX_LENGTH_MEMBER_TAG_NAME = 32;

    public const int MAX_MEMBER_TAGS_PER_COMMUNITY = 100;

    // The tags of one member in one community. An invitation or an invite link holds as many at most (#1390, #1391)
    public const int MAX_MEMBER_TAGS_PER_MEMBER = 20;

    // Invite links (#1391, ADR 0062). WARNING: aligned with constants.ts. The name column holds 64 characters
    public const int MIN_LENGTH_INVITE_LINK_NAME = 2;

    public const int MAX_LENGTH_INVITE_LINK_NAME = 64;

    // The links of one community, whatever their state
    public const int MAX_INVITE_LINKS_PER_COMMUNITY = 50;

    // The largest maximum number of joins of one link
    public const int MAX_INVITE_LINK_USES = 10000;

    // The openings of a link, the reads of its page and the API reads of the person per minute, each in its own limiter, per
    // account or per IP address of a visitor
    public const int INVITE_LINK_READS_PER_MINUTE = 20;

    // Lifetimes of a personal API token, in days, or "never" (#1267, ADR 0022)
    // WARNING: aligned with API_TOKEN_EXPIRY_CHOICES (jetstream-inertia.intf.ts)
    public const array API_TOKEN_EXPIRY_CHOICES = ['30', '90', '365', 'never'];

    public const string API_TOKEN_DEFAULT_EXPIRY = '90';

    // Community name checks (#1136) per minute and per user: the forms send one after each pause in the typing
    public const int NAME_CHECKS_PER_MINUTE = 60;

    // Joins of an open community (#1486) per minute and per user
    public const int JOINS_PER_MINUTE = 10;

    // Ownership transfers (#1339) per minute, per session or per API token: each one checks the password of the owner
    public const int OWNERSHIP_TRANSFERS_PER_MINUTE = 5;

    // Deletions of the own account through the API (#1492) per minute, per session or per API token: each one checks the
    // password
    public const int ACCOUNT_DELETIONS_PER_MINUTE = 3;

    // Wrong codes of two-factor authentication per account, for every place that checks a code together (App\Support\
    // SecondFactorTries): the challenge of the sign-in, the API sign-in, the deletion of the account, the transfer of a
    // community. Per minute AND per hour
    public const int SECOND_FACTOR_TRIES_PER_MINUTE = 5;

    public const int SECOND_FACTOR_TRIES_PER_HOUR = 10;

    // The challenge of the second factor of a web sign-in ends after these minutes: the person signs in again
    public const int SECOND_FACTOR_CHALLENGE_MINUTES = 10;

    // Wrong current passwords for every place that asks for the current password together (App\Support\PasswordTries):
    // per session that a sign-in made, per account for the sessions that the "Remember me" cookie made, and per account
    // for the API tokens. After them a session ends, and the tokens wait until the minutes are over
    public const int PASSWORD_TRIES = 5;

    public const int PASSWORD_TRIES_MINUTES = 15;

    // The link of a change of the email address (#1706): send it again, cancel the change, open it. Per minute and per user,
    // for the web and the API together. A link is a mail
    public const int EMAIL_CHANGES_PER_MINUTE = 6;

    // Changes of the email address in the profile form per day and per account: each one sends a mail to a new address
    public const int EMAIL_CHANGES_PER_DAY = 5;

    // The mails "This address already has an account" of a change of the address, per recipient and hour (ADR 0058): one a
    // minute at most too
    public const int EMAIL_ADDRESS_HAS_ACCOUNT_MAILS_PER_HOUR = 5;

    // Markdown preview requests (editor) per minute and per user
    public const int MARKDOWN_PREVIEWS_PER_MINUTE = 60;

    // Member lists per minute and per user (#148, ADR 0056): the member directory, and the API list that the directory, the
    // member management and the member fields read. A search sends one after each pause in the typing
    public const int MEMBER_LISTS_PER_MINUTE = 60;

    // The members of one page of the member directory (#148). WARNING: aligned with COMMUNITY_MEMBERS_PAGE_SIZE (constants.ts)
    public const int COMMUNITY_MEMBERS_PAGE_SIZE = 20;

    // The search text of a member list: the server keeps its first characters (#148). WARNING: aligned with
    // MEMBER_SEARCH_MAX_LENGTH (constants.ts)
    public const int MEMBER_SEARCH_MAX_LENGTH = 100;

    // Posts created per minute and per user
    public const int POSTS_PER_MINUTE = 5;

    // New events and copies of events per member (#1513)
    public const int EVENTS_PER_MINUTE = 5;

    // Answers to events, and clears of an answer, per member (#1515)
    public const int EVENT_ANSWERS_PER_MINUTE = 30;

    // The calendar files of events (#1515): a member adds a few events to a calendar, a script reads more, not hundreds
    public const int CALENDAR_FILES_PER_MINUTE = 30;

    // Starts and ends of a sign-in with the OpenID Connect provider per minute and per IP address (#1385)
    public const int OIDC_REQUESTS_PER_MINUTE = 10;

    // Calls of the sign-in provider to the entitlements webhook per minute and per IP address (#1386, ADR 0047). The provider
    // joins the changes of one person into one call, and retries with a growing delay
    public const int OIDC_ENTITLEMENT_EVENTS_PER_MINUTE = 120;

    // Calls of the mail provider to its webhook per minute and per IP address (#1478, ADR 0054). Mail events come in bursts:
    // a send to 400 members gives 400 deliveries in a minute. A call over the limit is retried later by the provider
    public const int MAIL_WEBHOOK_EVENTS_PER_MINUTE = 600;

    // The time of a webhook call of the mail provider may differ from the clock of the server by this many seconds at most
    // (#1478): an older or a newer call is refused, so that a captured call cannot be sent again later
    public const int MAIL_WEBHOOK_TOLERANCE_SECONDS = 300;

    // The largest body of a webhook call of the mail provider (#1478): a real report is a few KB
    public const int MAIL_WEBHOOK_MAX_BODY_BYTES = 262144;

    // Soft bounces in a row (no delivery in between) that stop the notification email of an address, like a hard bounce (#1478)
    public const int EMAIL_SOFT_BOUNCES_TO_SUPPRESS = 3;

    // Calls of the stop link of a notification email per minute and per IP address, for its three routes together (#1478). The
    // limit runs before the check of the signature
    public const int MAIL_STOP_PER_ADDRESS_PER_MINUTE = 60;

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

    // "Next events" on the home and on the public page of a community (#1514). WARNING: aligned with NEXT_EVENTS (constants.ts)
    public const int NEXT_EVENTS = 3;

    // Pagination (ADR 0011: limit and offset, defaults of the NBB guide)
    public const int PAGINATION_DEFAULT_LIMIT = 10;

    public const int PAGINATION_MAX_LIMIT = 100;

    // Notifications (#1474, ADR 0042): the queues of the worker (it reads broadcasts, notifications, default, in this order)
    public const string QUEUE_BROADCASTS = 'broadcasts';

    public const string QUEUE_NOTIFICATIONS = 'notifications';

    // Captures of resources (ADR 0018): a queue of their own, read by a worker of its own, so that a long list of captures
    // never delays a live update, a notification or an account mail
    public const string QUEUE_CAPTURES = 'captures';

    // Email delivery (#1477, ADR 0054): the queue of the notification mail, read by a worker of its own. Code reads
    // config('mail.queue'), which defaults to this name (MAIL_QUEUE=default puts the mail on the first worker)
    public const string QUEUE_MAIL = 'mail';

    // A notification mail is tried for this many hours after it was due (queued, or the end of its delay), whatever the
    // tries of the worker: in that time a release by the rate limiter never fails it. After it, the mail is not sent late:
    // the worker fails the job
    public const int MAIL_NOTIFICATION_RETRY_HOURS = 2;

    // One attempt of a job of the mail queue runs at most this long: the default timeout of `queue:work`, that the mail
    // worker uses. SendInvitationImportMail sets it on the job, so the time holds also with another worker command
    public const int MAIL_JOB_TIMEOUT_SECONDS = 60;

    // A row of an import of invitations that stays `queued` counts as not sent (#1390, ADR 0061) this many minutes
    // after the retries of its mail and one attempt of its job are over: a job that started before the end of its
    // retries ends first
    public const int INVITATION_IMPORT_STALE_MARGIN_MINUTES = 15;

    // mail:check-budget warns the operator once a month when this share of the monthly quota is used (design 6.14.12)
    public const int MAIL_BUDGET_ALERT_PERCENT = 80;

    // app:health fails when a job of the mail queue waits for longer than this and no job of that queue ended in the same
    // time: the mail worker does not run
    public const int MAIL_QUEUE_STALE_MINUTES = 10;

    // A page of the list of notifications
    public const int NOTIFICATIONS_PER_PAGE = 20;

    // The list folds a row into "Older" and the count forgets it after this many days without news
    public const int NOTIFICATIONS_FOLD_DAYS = 14;

    // "Catch up" (#1476, ADR 0048): the card shows after this many days away, holds this many lines at most, and looks back
    // this many days at most. The old date waits in the session under this key
    public const int CATCH_UP_AWAY_DAYS = 7;

    public const int CATCH_UP_MAX_LINES = 5;

    public const int CATCH_UP_MAX_DAYS_BACK = 366;

    public const string SESSION_CATCH_UP_SINCE = 'catch_up_since';

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

    // The values of one instance (#1653, ADR 0055). Outside production, an operator and a contact address that are not
    // set get these placeholders (config/legal.php). In production they count as not set: the instance refuses to start
    public const string INSTANCE_PLACEHOLDER_OPERATOR = 'Example Operator';

    public const string INSTANCE_PLACEHOLDER_CONTACT_EMAIL = 'contact@example.com';

    // The address of the first user of the seeder when SEED_ADMIN_EMAIL is empty
    public const string INSTANCE_DEFAULT_SEED_ADMIN_EMAIL = 'admin@example.com';

    /**
     * The artisan commands that refuse to start in production while a required value of the instance is missing: the
     * long-running processes. The other commands run (key:generate, the caches, migrate, app:health)
     *
     * @var list<string>
     */
    public const array INSTANCE_CHECKED_COMMANDS = ['queue:work', 'queue:listen', 'schedule:work', 'schedule:run', 'reverb:start'];

    // WebSockets
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

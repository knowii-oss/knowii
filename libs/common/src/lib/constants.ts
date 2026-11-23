/**
 * Meta
 */
export const BUILD_TIME = new Date().toISOString();
export const BUILD_TIMESTAMP = Date.now();

/**
 * UI/UX
 */
export const MIN_ACTION_TIME = 250;
export const DEFAULT_TOAST_LIFE = 3000;
export const MAX_VISIBLE_TOASTS = 5;

/**
 * Themes
 * WARNING: the storage key and the colors MUST remain aligned with the inline theme script in resources/views/app.blade.php
 */
export const THEME_STORAGE_KEY = 'knowii-theme';
export const THEME_BACKGROUND_COLORS = {
  dark: '#303843',
  light: '#f5f7fa',
} as const;
export const DEFAULT_TEXTAREA_ROWS = 3;
export const USER_PROFILE_BIO_TEXTAREA_ROWS = 8;

/**
 * URLs
 */
export const HOME_URL = '/';
export const DASHBOARD_URL = 'dashboard';
export const CONTACT_URL = '/contact';

// Legal
export const TERMS_OF_SERVICE_URL = 'terms-of-service.show';
export const PRIVACY_POLICY_URL = 'privacy-policy.show';
export const DPA_URL = 'dpa.show';
export const IMPRINT_URL = 'imprint.show';
export const CONTENT_POLICY_URL = 'content-policy.show';
export const SECURITY_URL = 'security.show';

/**
 * The slugs of the legal pages (#1653, ADR 0055): the address of a page, and the prefix of its route name. An instance has
 * a page only when it has the text of that page: the shared prop `legalPages` lists the ones that exist.
 * WARNING: aligned with LegalPages::PAGES (back-end)
 */
export const LEGAL_PAGE_SLUGS = ['privacy-policy', 'terms-of-service', 'dpa', 'imprint', 'content-policy', 'security'] as const;
export type LegalPageSlug = (typeof LEGAL_PAGE_SLUGS)[number];

// User profile
export const USER_PROFILE_URL = 'profile.show';
// The notification settings of the member (#1497)
export const USER_NOTIFICATIONS_URL = 'profile.notifications';
export const USER_PROFILE_INFORMATION_UPDATE_URL = 'user-profile-information.update';
export const USER_PROFILE_PASSWORD_UPDATE_URL = 'user-password.update';

// Communities
export const COMMUNITY_URL = 'communities.show';
export const COMMUNITY_SETTINGS_URL = 'communities.settings';
export const COMMUNITY_ABOUT_URL = 'communities.about';
// "Join" on the page of an invite link (#1391): a POST that reads the link of the session. WARNING: aligned with routes/web.php
export const INVITE_LINK_JOIN_URL = 'invite-links.join';
// API tokens (#689): Jetstream routes
export const API_TOKENS_URL = 'api-tokens.index';
export const API_TOKENS_STORE_URL = 'api-tokens.store';
export const API_TOKENS_UPDATE_URL = 'api-tokens.update';
export const API_TOKENS_DESTROY_URL = 'api-tokens.destroy';

// Spaces
export const SPACE_URL = 'spaces.show';

// Posts
export const POST_URL = 'posts.show';
// A page of a page space (#1179)
export const PAGE_URL = 'pages.show';

// Members
export const MEMBER_URL = 'members.show';
// The member directory of a community (#148)
export const MEMBERS_URL = 'members.index';
// Direct messages (#1186)
export const MESSAGES_URL = 'messages.index';
export const CONVERSATION_URL = 'messages.show';

// Resources
export const RESOURCE_URL = 'resources.show';

// Events of an events space (#1514). WARNING: aligned with routes/web.php
export const EVENT_URL = 'events.show';
export const EVENT_CREATE_URL = 'events.create';
export const EVENT_EDIT_URL = 'events.edit';
// The query parameter of the page of an events space that chooses "Upcoming" or "Past". WARNING: aligned with SpaceController.php
export const EVENTS_SCOPE_QUERY_PARAM = 'scope';
// The page "My events" of the member (#1515): the events that they answered Yes or Replay only, and the ones they host
export const MY_EVENTS_URL = 'events.index';

// Authentication
export const LOGIN_URL = 'login';
// The redirect to the OpenID Connect provider of the instance, when it has one (#1385)
export const OIDC_REDIRECT_URL = 'oidc.redirect';
// A signed-in member connects a provider account: a POST, never a link (#1385)
export const OIDC_CONNECT_URL = 'oidc.connect';
// A signed-in member without a password confirms the deletion of the account with a new sign-in: a POST (#1521)
export const OIDC_CONFIRM_DELETION_URL = 'oidc.confirm-deletion';
export const REGISTER_URL = 'register';
export const LOGOUT_URL = 'logout';
export const PASSWORD_CONFIRM_URL = 'password.confirm';
// URL for sending password forgotten requests
export const EMAIL_PASSWORD_URL = 'password.email';
export const PASSWORD_CONFIRMATION_URL = 'password.confirmation';
// Sign in with a link sent by email (#703)
export const LOGIN_LINK_STORE_URL = 'login.link.store';
export const LOGIN_LINK_CONSUME_URL = 'login.link.consume';
// Two-factor authentication (#690): Fortify routes. WARNING: aligned with `php artisan route:list`
export const TWO_FACTOR_ENABLE_URL = 'two-factor.enable';
export const TWO_FACTOR_CONFIRM_URL = 'two-factor.confirm';
export const TWO_FACTOR_DISABLE_URL = 'two-factor.disable';
export const TWO_FACTOR_QR_CODE_URL = 'two-factor.qr-code';
export const TWO_FACTOR_SECRET_KEY_URL = 'two-factor.secret-key';
export const TWO_FACTOR_RECOVERY_CODES_URL = 'two-factor.recovery-codes';
export const TWO_FACTOR_REGENERATE_RECOVERY_CODES_URL = 'two-factor.regenerate-recovery-codes';
export const TWO_FACTOR_LOGIN_URL = 'two-factor.login.store';
export const PASSWORD_UPDATE_URL = 'password.update';
export const FORGOT_PASSWORD_URL = 'password.request';
// URL for sending email verification requests
export const EMAIL_VERIFICATION_URL = 'verification.send';
// A change of the email address that waits for its confirmation (#1706): send the link again, cancel the change
export const EMAIL_CHANGE_RESEND_URL = 'email-change.resend';
export const EMAIL_CHANGE_CANCEL_URL = 'email-change.cancel';
export const EMAIL_VERIFICATION_STATUS_LINK_SENT = 'verification-link-sent';
export const DESTROY_OTHER_BROWSER_SESSIONS_URL = 'other-browser-sessions.destroy';
export const DELETE_USER_URL = 'current-user.destroy';

/**
 * Regexes and other constants
 * WARNING: Those MUST remain aligned with those defined in Constants.php on the back-end
 */
export const MIN_LENGTH_USER_USERNAME = 3;
export const MAX_LENGTH_USER_USERNAME = 36;
export const ALLOWED_USER_USERNAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-']+$/;

// One line under the name of the member (#1688). WARNING: aligned with MAX_LENGTH_USER_HEADLINE (Constants.php)
export const MAX_LENGTH_USER_HEADLINE = 80;
export const MAX_LENGTH_USER_LOCATION = 128;
export const MAX_LENGTH_USER_BIO = 512;
export const MAX_LENGTH_USER_PHONE = 48;

// The email field and the hidden field of the newsletter form (the names come from the waitlist, removed in #1574).
// WARNING: aligned with Constants.php
export const MAX_LENGTH_WAITLIST_EMAIL = 255;
export const WAITLIST_HONEYPOT_FIELD = 'website';
// The hidden field of the sign-up form (#737). WARNING: aligned with SIGNUP_HONEYPOT_FIELD (Constants.php)
export const SIGNUP_HONEYPOT_FIELD = 'website';
export const USER_PHONE_REGEX = /^[0-9-+()./ ]+$/;

// Community
export const MIN_LENGTH_COMMUNITY_NAME = 3;
export const MAX_LENGTH_COMMUNITY_NAME = 64;
export const MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
export const ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-@' ]+$/;

// Space groups and spaces. WARNING: aligned with Constants.php
export const MIN_LENGTH_SPACE_GROUP_NAME = 2;
export const MAX_LENGTH_SPACE_GROUP_NAME = 64;
export const MIN_LENGTH_SPACE_NAME = 2;
export const MAX_LENGTH_SPACE_NAME = 64;
export const MAX_LENGTH_SPACE_DESCRIPTION = 255;
export const MAX_LENGTH_SPACE_EMOJI = 16;
// Link spaces (#1294). WARNING: aligned with MAX_LENGTH_SPACE_URL (Constants.php)
export const MAX_LENGTH_SPACE_URL = 2048;

// Posts. WARNING: aligned with Constants.php and SpaceController::POSTS_PER_PAGE
export const MIN_LENGTH_POST_TITLE = 3;
export const MAX_LENGTH_POST_TITLE = 200;
export const MAX_LENGTH_POST_BODY = 50000;
// Events of an events space (#1513, ADR 0053). WARNING: aligned with Constants.php
export const MIN_LENGTH_EVENT_TITLE = 3;
export const MAX_LENGTH_EVENT_TITLE = 200;
export const MAX_LENGTH_EVENT_BODY = 20000;
export const MAX_LENGTH_EVENT_MEETING_URL = 2048;
export const MAX_LENGTH_EVENT_PLACE = 255;
export const MAX_LENGTH_EVENT_COVER_ALT = 125;
export const MAX_EVENT_DURATION_HOURS = 24;
// After the event (#1516): the replay link and the notes
export const MAX_LENGTH_EVENT_REPLAY_URL = 2048;
export const MAX_LENGTH_EVENT_NOTES = 20000;
// The limit of places of an event (#1515): it counts the Yes answers
export const MIN_EVENT_CAPACITY = 1;
export const MAX_EVENT_CAPACITY = 5000;
// The events of one page of the list of an events space (#1514). WARNING: aligned with SpaceController::EVENTS_PER_PAGE
export const EVENTS_PER_PAGE = 20;
// The names of one load of the list of the answers of an event, for its host, the owner and the admins (#1515)
export const EVENT_ANSWERS_PER_PAGE = 20;
// "Next events" on the home and on the public page of a community (#1514). WARNING: aligned with Constants::NEXT_EVENTS
export const NEXT_EVENTS = 3;
// Chat messages (#1185). WARNING: aligned with Constants.php
export const MAX_LENGTH_CHAT_MESSAGE = 10000;
// The members of a conversation, the author included (#1186). WARNING: aligned with Conversation::MAX_PARTICIPANTS
export const MAX_CONVERSATION_PARTICIPANTS = 10;
export const POSTS_PER_PAGE = 20;
export const POST_EDITOR_ROWS = 12;
export const MAX_LENGTH_COMMENT_BODY = 10000;
export const COMMENT_EDITOR_ROWS = 4;

// Image uploads. WARNING: aligned with Constants.php
export const MAX_UPLOAD_SIZE_BYTES = 10 * 1024 * 1024;
export const MAX_LENGTH_UPLOAD_ALT = 500;
// Several images (#1207): how many go in at a time, and how many one post, comment, message or page holds
export const MAX_IMAGES_PER_INSERT = 10;
export const MAX_IMAGES_PER_CONTENT = 20;

// Lesson quizzes (#1335, ADR 0038). WARNING: aligned with Constants.php
export const MIN_QUIZ_QUESTIONS = 1;
export const MAX_QUIZ_QUESTIONS = 50;
export const MIN_QUIZ_CHOICES = 2;
export const MAX_QUIZ_CHOICES = 8;
export const MAX_LENGTH_QUIZ_PROMPT = 2000;
export const MAX_LENGTH_QUIZ_EXPLANATION = 2000;
export const MAX_LENGTH_QUIZ_CHOICE = 300;
export const DEFAULT_QUIZ_PASS_MARK = 70;

// Course materials (#1336). WARNING: aligned with Constants.php and CourseMaterials.php
export const MIN_LENGTH_COURSE_MATERIAL_TITLE = 1;
export const MAX_LENGTH_COURSE_MATERIAL_TITLE = 120;
export const MAX_COURSE_MATERIALS_PER_TARGET = 20;
// The default of the instance (COURSE_MATERIALS_MAX_MB): the server has the last word
export const MAX_COURSE_MATERIAL_SIZE_BYTES = 50 * 1024 * 1024;
export const COURSE_MATERIAL_EXTENSIONS = [
  'pdf',
  'zip',
  'epub',
  'json',
  'txt',
  'md',
  'csv',
  'docx',
  'xlsx',
  'pptx',
  'odt',
  'ods',
  'odp',
  'png',
  'jpg',
  'jpeg',
  'webp',
] as const;
export const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'] as const;

// Community resource
export const MIN_LENGTH_COMMUNITY_RESOURCE_NAME = 3;
export const MAX_LENGTH_COMMUNITY_RESOURCE_NAME = 64;
export const ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-@,' ]+$/;

export const MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION = 255;
// WARNING: aligned with SpaceController::RESOURCES_PER_PAGE
export const RESOURCES_PER_PAGE = 24;

/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
export const PING_API_PATH = `${API_BASE_PATH}/ping`;

// Newsletter of the footer (#938)
export const NEWSLETTER_API_PATH = `${API_BASE_PATH}/newsletter`;

// Users
export const USERS_API_BASE_PATH = `${API_BASE_PATH}/users`;
export const USERS_API_IS_USERNAME_AVAILABLE_PATH = `${USERS_API_BASE_PATH}/is-username-available`;

// Communities
export const USER_AVATAR_API_PATH = `${API_BASE_PATH}/user/avatar`;
// Show or hide when the current user is online (#1187)
export const USER_ONLINE_STATUS_API_PATH = `${API_BASE_PATH}/user/online-status`;
// The members the current user blocked (#1186)
// The time zone and the pop-up switch of the member (#1476, ADR 0048)
export const USER_TIME_ZONE_API_PATH = `${API_BASE_PATH}/user/time-zone`;
export const USER_NOTIFICATION_SETTINGS_API_PATH = `${API_BASE_PATH}/user/notification-settings`;
export const USER_BLOCKS_API_PATH = `${API_BASE_PATH}/user/blocks`;
// The provider accounts that the member connected (#1385, ADR 0045)
export const USER_IDENTITIES_API_PATH = `${API_BASE_PATH}/user/identities`;
export const USER_FREQUENT_EMOJI_API_PATH = `${API_BASE_PATH}/user/frequent-emoji`;
// "My events" of the member, in all their communities (#1515). WARNING: aligned with ApiRoutes::USER_EVENTS
export const USER_EVENTS_API_PATH = `${API_BASE_PATH}/user/events`;
export const COMMUNITY_API_BASE_PATH = `${API_BASE_PATH}/communities`;
// Whether a shared community can take a name (#1136). WARNING: aligned with ApiRoutes.php
export const COMMUNITY_API_IS_NAME_AVAILABLE_PATH = `${COMMUNITY_API_BASE_PATH}/is-name-available`;

export const COMMUNITY_API_PATH_PARAM_COMMUNITY = '{communityCuid}';
export const COMMUNITY_API_PATH = `${API_BASE_PATH}/communities/${COMMUNITY_API_PATH_PARAM_COMMUNITY}`;

// Space groups and spaces (#1173). WARNING: aligned with ApiRoutes.php
export const SPACE_API_PARAM_COMMUNITY = COMMUNITY_API_PATH_PARAM_COMMUNITY;
export const SPACE_API_PARAM_SPACE_GROUP = '{spaceGroupCuid}';
export const SPACE_API_PARAM_SPACE = '{spaceCuid}';
// Moderator rights (#1240)
export const COMMUNITY_MODERATOR_PERMISSIONS_API_PATH = `${COMMUNITY_API_PATH}/moderator-permissions`;
// The owner gives the community to an admin (#1339)
export const COMMUNITY_OWNERSHIP_API_PATH = `${COMMUNITY_API_PATH}/ownership`;
export const COMMUNITY_SPACE_GROUPS_API_PATH = `${COMMUNITY_API_PATH}/space-groups`;
export const COMMUNITY_SPACE_GROUPS_REORDER_API_PATH = `${COMMUNITY_SPACE_GROUPS_API_PATH}/reorder`;
export const COMMUNITY_SPACE_GROUP_API_PATH = `${COMMUNITY_SPACE_GROUPS_API_PATH}/${SPACE_API_PARAM_SPACE_GROUP}`;
export const COMMUNITY_SPACE_GROUP_SPACES_REORDER_API_PATH = `${COMMUNITY_SPACE_GROUP_API_PATH}/spaces/reorder`;
export const COMMUNITY_SPACES_API_PATH = `${COMMUNITY_API_PATH}/spaces`;
export const COMMUNITY_SPACE_API_PATH = `${COMMUNITY_SPACES_API_PATH}/${SPACE_API_PARAM_SPACE}`;
// Who is online now (#1187, ADR 0027)
export const COMMUNITY_ONLINE_MEMBERS_API_PATH = `${COMMUNITY_API_PATH}/online-members`;
export const COMMUNITY_SPACE_ONLINE_MEMBERS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/online-members`;

// Resources of resources spaces (ADR 0015)
export const COMMUNITY_SPACE_RESOURCES_API_PATH = `${COMMUNITY_SPACE_API_PATH}/resources`;
export const SPACE_API_PARAM_RESOURCE = '{resourceCuid}';
export const COMMUNITY_SPACE_RESOURCE_API_PATH = `${COMMUNITY_SPACE_RESOURCES_API_PATH}/${SPACE_API_PARAM_RESOURCE}`;
// Comments on resources (#1034)
// Start the capture again (ADR 0018)
export const COMMUNITY_SPACE_RESOURCE_CAPTURE_API_PATH = `${COMMUNITY_SPACE_RESOURCE_API_PATH}/capture`;
export const COMMUNITY_SPACE_RESOURCE_COMMENTS_API_PATH = `${COMMUNITY_SPACE_RESOURCE_API_PATH}/comments`;
export const COMMUNITY_SPACE_RESOURCE_COMMENT_API_PATH = `${COMMUNITY_SPACE_RESOURCE_COMMENTS_API_PATH}/{commentCuid}`;

// Posts (#1174)
export const SPACE_API_PARAM_POST = '{postCuid}';
export const COMMUNITY_SPACE_POSTS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/posts`;
export const COMMUNITY_SPACE_POST_API_PATH = `${COMMUNITY_SPACE_POSTS_API_PATH}/${SPACE_API_PARAM_POST}`;
// Events of events spaces (#1513, ADR 0053). WARNING: aligned with ApiRoutes.php
export const SPACE_API_PARAM_EVENT = '{eventCuid}';
export const COMMUNITY_SPACE_EVENTS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/events`;
export const COMMUNITY_SPACE_EVENT_API_PATH = `${COMMUNITY_SPACE_EVENTS_API_PATH}/${SPACE_API_PARAM_EVENT}`;
export const COMMUNITY_SPACE_EVENT_STATE_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/state`;
export const COMMUNITY_SPACE_EVENT_DUPLICATE_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/duplicate`;
// The answer of the member to an event, and the answers that the host and the team read (#1515)
export const COMMUNITY_SPACE_EVENT_ANSWER_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/answer`;
export const COMMUNITY_SPACE_EVENT_ANSWERS_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/answers`;
// The calendar file of an event, for a member (#1515): a plain link downloads it
export const COMMUNITY_SPACE_EVENT_CALENDAR_FILE_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/calendar-file`;
// The comments of an event (#1516), as the comments of a resource. No reaction on the event itself
export const COMMUNITY_SPACE_EVENT_COMMENTS_API_PATH = `${COMMUNITY_SPACE_EVENT_API_PATH}/comments`;
export const COMMUNITY_SPACE_EVENT_COMMENT_API_PATH = `${COMMUNITY_SPACE_EVENT_COMMENTS_API_PATH}/{commentCuid}`;
export const COMMUNITY_SPACE_EVENT_COMMENT_REACTIONS_API_PATH = `${COMMUNITY_SPACE_EVENT_COMMENT_API_PATH}/reactions`;
export const COMMUNITY_EVENTS_API_PATH = `${COMMUNITY_API_PATH}/events`;
// Messages of chat spaces (#1185)
export const SPACE_API_PARAM_MESSAGE = '{messageCuid}';
export const COMMUNITY_SPACE_MESSAGES_API_PATH = `${COMMUNITY_SPACE_API_PATH}/messages`;
export const COMMUNITY_SPACE_MESSAGES_READ_API_PATH = `${COMMUNITY_SPACE_MESSAGES_API_PATH}/read`;
export const COMMUNITY_SPACE_MESSAGE_API_PATH = `${COMMUNITY_SPACE_MESSAGES_API_PATH}/${SPACE_API_PARAM_MESSAGE}`;
export const COMMUNITY_SPACE_MESSAGE_THREAD_API_PATH = `${COMMUNITY_SPACE_MESSAGE_API_PATH}/thread`;
export const COMMUNITY_SPACE_MESSAGE_REACTIONS_API_PATH = `${COMMUNITY_SPACE_MESSAGE_API_PATH}/reactions`;
// Direct messages (#1186, ADR 0028)
export const CONVERSATION_API_PARAM_CONVERSATION = '{conversationCuid}';
export const COMMUNITY_CONVERSATIONS_API_PATH = `${COMMUNITY_API_PATH}/conversations`;
export const COMMUNITY_CONVERSATION_API_PATH = `${COMMUNITY_CONVERSATIONS_API_PATH}/${CONVERSATION_API_PARAM_CONVERSATION}`;
export const COMMUNITY_CONVERSATION_MESSAGES_API_PATH = `${COMMUNITY_CONVERSATION_API_PATH}/messages`;
export const COMMUNITY_CONVERSATION_READ_API_PATH = `${COMMUNITY_CONVERSATION_API_PATH}/read`;
export const COMMUNITY_CONVERSATION_MESSAGE_API_PATH = `${COMMUNITY_CONVERSATION_MESSAGES_API_PATH}/${SPACE_API_PARAM_MESSAGE}`;
export const COMMUNITY_CONVERSATION_MESSAGE_REACTIONS_API_PATH = `${COMMUNITY_CONVERSATION_MESSAGE_API_PATH}/reactions`;
// Notifications of the current member, in every community (#1474, #1484, ADR 0042)
export const NOTIFICATION_API_PARAM_NOTIFICATION = '{notificationCuid}';
export const NOTIFICATIONS_API_PATH = `${API_BASE_PATH}/notifications`;
export const NOTIFICATIONS_COUNT_API_PATH = `${NOTIFICATIONS_API_PATH}/count`;
export const NOTIFICATIONS_SEEN_API_PATH = `${NOTIFICATIONS_API_PATH}/seen`;
export const NOTIFICATION_API_PATH = `${NOTIFICATIONS_API_PATH}/${NOTIFICATION_API_PARAM_NOTIFICATION}`;
export const NOTIFICATION_READ_API_PATH = `${NOTIFICATION_API_PATH}/read`;
export const NOTIFICATION_UNREAD_API_PATH = `${NOTIFICATION_API_PATH}/unread`;
// "I am caught up" (#1497): the open rows of "Following" are read
export const NOTIFICATIONS_CAUGHT_UP_API_PATH = `${NOTIFICATIONS_API_PATH}/caught-up`;
// The data of the "Catch up" card, and the visit of a space (#1476, ADR 0048)
export const COMMUNITY_CATCH_UP_API_PATH = `${COMMUNITY_API_PATH}/catch-up`;
export const COMMUNITY_SPACE_VISIT_API_PATH = `${COMMUNITY_SPACE_API_PATH}/visit`;
// The page "Notifications" of the member (#1476)
export const NOTIFICATIONS_URL = 'notifications.index';
// The levels of the current member for the spaces of a community, and for one space (#1497)
export const COMMUNITY_NOTIFICATION_LEVELS_API_PATH = `${COMMUNITY_API_PATH}/notification-levels`;
export const COMMUNITY_SPACE_NOTIFICATION_LEVEL_API_PATH = `${COMMUNITY_SPACE_API_PATH}/notification-level`;
// The follow state of a post thread and of a chat thread: read (GET), follow (PUT), stop following (DELETE), mute (PUT /mute) and its undo (DELETE /mute)
export const COMMUNITY_SPACE_POST_FOLLOW_API_PATH = `${COMMUNITY_SPACE_POST_API_PATH}/follow`;
export const COMMUNITY_SPACE_POST_MUTE_API_PATH = `${COMMUNITY_SPACE_POST_API_PATH}/mute`;
export const COMMUNITY_SPACE_MESSAGE_FOLLOW_API_PATH = `${COMMUNITY_SPACE_MESSAGE_API_PATH}/follow`;
export const COMMUNITY_SPACE_MESSAGE_MUTE_API_PATH = `${COMMUNITY_SPACE_MESSAGE_API_PATH}/mute`;
// Notifications per page (WARNING: aligned with Constants::NOTIFICATIONS_PER_PAGE)
export const NOTIFICATIONS_PER_PAGE = 20;
// Pages of page spaces (#1179)
export const SPACE_API_PARAM_PAGE = '{pageCuid}';
export const COMMUNITY_SPACE_PAGES_API_PATH = `${COMMUNITY_SPACE_API_PATH}/pages`;
export const COMMUNITY_SPACE_PAGES_ORDER_API_PATH = `${COMMUNITY_SPACE_PAGES_API_PATH}/order`;
export const COMMUNITY_SPACE_PAGE_API_PATH = `${COMMUNITY_SPACE_PAGES_API_PATH}/${SPACE_API_PARAM_PAGE}`;
// Course progress of the current member (#1304)
export const COMMUNITY_SPACE_PROGRESS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/progress`;
// Supplementary materials of course spaces (#1336). WARNING: aligned with ApiRoutes.php
export const SPACE_API_PARAM_MATERIAL = '{materialCuid}';
export const COMMUNITY_SPACE_MATERIALS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/materials`;
export const COMMUNITY_SPACE_MATERIALS_ORDER_API_PATH = `${COMMUNITY_SPACE_MATERIALS_API_PATH}/order`;
export const COMMUNITY_SPACE_MATERIAL_API_PATH = `${COMMUNITY_SPACE_MATERIALS_API_PATH}/${SPACE_API_PARAM_MATERIAL}`;
export const COMMUNITY_SPACE_MATERIAL_DOWNLOAD_API_PATH = `${COMMUNITY_SPACE_MATERIAL_API_PATH}/download`;
export const COMMUNITY_SPACE_PAGE_PROGRESS_API_PATH = `${COMMUNITY_SPACE_PAGE_API_PATH}/progress`;
// The quiz of a lesson (#1335). WARNING: aligned with ApiRoutes.php
export const COMMUNITY_SPACE_PAGE_QUIZ_API_PATH = `${COMMUNITY_SPACE_PAGE_API_PATH}/quiz`;
export const COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS_API_PATH = `${COMMUNITY_SPACE_PAGE_QUIZ_API_PATH}/attempts`;
export const COMMUNITY_SPACE_POST_PIN_API_PATH = `${COMMUNITY_SPACE_POST_API_PATH}/pin`;

// Markdown preview (same rendering as the saved content)
export const MARKDOWN_PREVIEW_API_PATH = `${API_BASE_PATH}/markdown/preview`;

// Comments (#1175)
export const SPACE_API_PARAM_COMMENT = '{commentCuid}';
export const COMMUNITY_SPACE_POST_COMMENTS_API_PATH = `${COMMUNITY_SPACE_POST_API_PATH}/comments`;
export const COMMUNITY_SPACE_POST_COMMENT_API_PATH = `${COMMUNITY_SPACE_POST_COMMENTS_API_PATH}/${SPACE_API_PARAM_COMMENT}`;

// Members (#1177: mention autocomplete, #1242: member management). WARNING: aligned with ApiRoutes.php
export const COMMUNITY_MEMBERS_API_PATH = `${COMMUNITY_API_PATH}/members`;
export const COMMUNITY_API_PARAM_MEMBER = '{memberCuid}';
export const COMMUNITY_MEMBER_API_PATH = `${COMMUNITY_MEMBERS_API_PATH}/${COMMUNITY_API_PARAM_MEMBER}`;
// The membership of the current user: DELETE to leave the community (#1338), POST to join an open community (#1486)
export const COMMUNITY_MEMBERSHIP_API_PATH = `${COMMUNITY_API_PATH}/membership`;
// Who can join the community, and the people whom a manager removed (#1486). WARNING: aligned with ApiRoutes.php
export const COMMUNITY_JOIN_POLICY_API_PATH = `${COMMUNITY_API_PATH}/join-policy`;
// Search engines may index the public spaces (#1487). WARNING: aligned with ApiRoutes.php
export const COMMUNITY_INDEXING_API_PATH = `${COMMUNITY_API_PATH}/indexing`;
export const COMMUNITY_JOIN_BLOCKS_API_PATH = `${COMMUNITY_API_PATH}/join-blocks`;
export const COMMUNITY_JOIN_BLOCK_API_PATH = `${COMMUNITY_JOIN_BLOCKS_API_PATH}/{memberCuid}`;
export const COMMUNITY_INVITATIONS_API_PATH = `${COMMUNITY_API_PATH}/invitations`;
export const COMMUNITY_API_PARAM_INVITATION = '{invitationCuid}';
export const COMMUNITY_INVITATION_API_PATH = `${COMMUNITY_INVITATIONS_API_PATH}/${COMMUNITY_API_PARAM_INVITATION}`;
// The largest page of the pending invitations (#1390). WARNING: aligned with MAX_INVITATIONS_PAGE_LIMIT (Constants.php)
export const MAX_INVITATIONS_PAGE_LIMIT = 1000;
// The pending invitations of the settings load this many at a time (#1390)
export const INVITATIONS_PAGE_SIZE = 20;
// Invitations from a file (#1390, ADR 0061). WARNING: aligned with ApiRoutes.php and Constants.php
export const COMMUNITY_INVITATION_IMPORTS_API_PATH = `${COMMUNITY_API_PATH}/invitation-imports`;
export const COMMUNITY_API_PARAM_INVITATION_IMPORT = '{invitationImport}';
export const COMMUNITY_INVITATION_IMPORT_API_PATH = `${COMMUNITY_INVITATION_IMPORTS_API_PATH}/${COMMUNITY_API_PARAM_INVITATION_IMPORT}`;
export const COMMUNITY_INVITATION_IMPORT_ROWS_API_PATH = `${COMMUNITY_INVITATION_IMPORT_API_PATH}/rows`;
export const COMMUNITY_INVITATION_IMPORT_CONFIRM_API_PATH = `${COMMUNITY_INVITATION_IMPORT_API_PATH}/confirm`;
export const COMMUNITY_INVITATION_IMPORT_RESEND_API_PATH = `${COMMUNITY_INVITATION_IMPORT_API_PATH}/resend`;
export const MAX_INVITATION_IMPORT_ROWS = 1000;
export const MAX_INVITATION_IMPORT_KILOBYTES = 1024;
export const MAX_INVITATION_IMPORT_COLUMNS = 20;
export const MAX_INVITATION_IMPORT_CELL_LENGTH = 1000;
export const INVITATION_IMPORT_RESEND_AFTER_DAYS = 3;
export const INVITATION_IMPORTS_PER_MINUTE = 5;
// The web pages of the invitations from a file (routes/web.php)
export const INVITATION_IMPORTS_URL = 'invitation-imports.index';
export const INVITATION_IMPORT_URL = 'invitation-imports.show';
// The file that the page "Invite from a file" offers as a template (public/templates)
export const INVITATION_IMPORT_TEMPLATE_PATH = '/templates/invitations.csv';
// The members of one page of the member management and of the member directory (#148). WARNING: aligned with
// COMMUNITY_MEMBERS_PAGE_SIZE (Constants.php)
export const COMMUNITY_MEMBERS_PAGE_SIZE = 20;
// The search text of a member list: the server keeps its first characters (#148). WARNING: aligned with
// MEMBER_SEARCH_MAX_LENGTH (Constants.php)
export const MEMBER_SEARCH_MAX_LENGTH = 100;

// Access groups (#1241, ADR 0025). WARNING: aligned with ApiRoutes.php and Constants.php
export const COMMUNITY_ACCESS_GROUPS_API_PATH = `${COMMUNITY_API_PATH}/access-groups`;
export const COMMUNITY_API_PARAM_ACCESS_GROUP = '{accessGroupCuid}';
export const COMMUNITY_ACCESS_GROUP_API_PATH = `${COMMUNITY_ACCESS_GROUPS_API_PATH}/${COMMUNITY_API_PARAM_ACCESS_GROUP}`;
export const COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH = `${COMMUNITY_ACCESS_GROUP_API_PATH}/members`;
export const COMMUNITY_ACCESS_GROUP_MEMBER_API_PATH = `${COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH}/${COMMUNITY_API_PARAM_MEMBER}`;
export const COMMUNITY_ACCESS_GROUP_SPACE_GROUPS_API_PATH = `${COMMUNITY_ACCESS_GROUP_API_PATH}/space-groups`;
// The entitlement keys that give an access group (#1386, ADR 0047)
export const COMMUNITY_ACCESS_GROUP_RULES_API_PATH = `${COMMUNITY_ACCESS_GROUP_API_PATH}/rules`;
// WARNING: aligned with Constants.php (MAX_LENGTH_ENTITLEMENT_KEY, ENTITLEMENT_KEY_PATTERN, MAX_ENTITLEMENT_RULES_PER_GROUP)
export const MAX_LENGTH_ENTITLEMENT_KEY = 100;
export const ENTITLEMENT_KEY_PATTERN = /^[A-Za-z0-9._-]+$/;
export const MAX_ENTITLEMENT_RULES_PER_GROUP = 20;
// Member tags (#1389, ADR 0060). WARNING: aligned with ApiRoutes.php and Constants.php
export const COMMUNITY_MEMBER_TAGS_API_PATH = `${COMMUNITY_API_PATH}/member-tags`;
export const COMMUNITY_API_PARAM_MEMBER_TAG = '{memberTagCuid}';
export const COMMUNITY_MEMBER_TAG_API_PATH = `${COMMUNITY_MEMBER_TAGS_API_PATH}/${COMMUNITY_API_PARAM_MEMBER_TAG}`;
export const COMMUNITY_MEMBER_TAG_MEMBERS_API_PATH = `${COMMUNITY_MEMBER_TAG_API_PATH}/members`;
export const COMMUNITY_MEMBER_TAG_MEMBER_API_PATH = `${COMMUNITY_MEMBER_TAG_MEMBERS_API_PATH}/${COMMUNITY_API_PARAM_MEMBER}`;
// The query parameter of the filter of the member list by a tag (T3, #1389)
export const COMMUNITY_MEMBERS_PARAM_MEMBER_TAG = 'memberTag';
export const MIN_LENGTH_MEMBER_TAG_NAME = 2;
export const MAX_LENGTH_MEMBER_TAG_NAME = 32;
export const MAX_MEMBER_TAGS_PER_COMMUNITY = 100;
export const MAX_MEMBER_TAGS_PER_MEMBER = 20;
export const MIN_LENGTH_ACCESS_GROUP_NAME = 2;
export const MAX_LENGTH_ACCESS_GROUP_NAME = 64;
export const MAX_LENGTH_ACCESS_GROUP_DESCRIPTION = 500;
// WARNING: aligned with MAX_ACCESS_GROUPS_PER_COMMUNITY (Constants.php)
export const MAX_ACCESS_GROUPS_PER_COMMUNITY = 50;
// Invite links (#1391, ADR 0062). WARNING: aligned with ApiRoutes.php and Constants.php
export const COMMUNITY_INVITE_LINKS_API_PATH = `${COMMUNITY_API_PATH}/invite-links`;
export const COMMUNITY_API_PARAM_INVITE_LINK = '{inviteLinkCuid}';
export const COMMUNITY_INVITE_LINK_API_PATH = `${COMMUNITY_INVITE_LINKS_API_PATH}/${COMMUNITY_API_PARAM_INVITE_LINK}`;
export const COMMUNITY_INVITE_LINK_USES_API_PATH = `${COMMUNITY_INVITE_LINK_API_PATH}/uses`;
export const INVITE_LINK_API_PARAM_TOKEN = '{inviteLinkToken}';
export const INVITE_LINK_API_PATH = `${API_BASE_PATH}/invite-links/${INVITE_LINK_API_PARAM_TOKEN}`;
export const INVITE_LINK_MEMBERSHIP_API_PATH = `${INVITE_LINK_API_PATH}/membership`;
export const MIN_LENGTH_INVITE_LINK_NAME = 2;
export const MAX_LENGTH_INVITE_LINK_NAME = 64;
export const MAX_INVITE_LINKS_PER_COMMUNITY = 50;
export const MAX_INVITE_LINK_USES = 10000;
export const INVITE_LINK_READS_PER_MINUTE = 20;
export const MENTION_SUGGESTIONS_LIMIT = 8;

// Image uploads (#1178)
export const COMMUNITY_UPLOADS_API_PATH = `${COMMUNITY_API_PATH}/uploads`;

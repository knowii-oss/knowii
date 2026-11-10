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
  dark: '#37404c',
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

// User profile
export const USER_PROFILE_URL = 'profile.show';
export const USER_PROFILE_INFORMATION_UPDATE_URL = 'user-profile-information.update';
export const USER_PROFILE_PASSWORD_UPDATE_URL = 'user-password.update';

// Communities
export const COMMUNITY_URL = 'communities.show';
export const COMMUNITY_SETTINGS_URL = 'communities.settings';
export const COMMUNITY_ABOUT_URL = 'communities.about';
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
// Direct messages (#1186)
export const MESSAGES_URL = 'messages.index';
export const CONVERSATION_URL = 'messages.show';

// Resources
export const RESOURCE_URL = 'resources.show';

// Authentication
export const LOGIN_URL = 'login';
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

export const MAX_LENGTH_USER_LOCATION = 128;
export const MAX_LENGTH_USER_BIO = 512;
export const MAX_LENGTH_USER_PHONE = 48;

// Waitlist. WARNING: aligned with Constants.php
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

// Waitlist
export const WAITLIST_API_PATH = `${API_BASE_PATH}/waitlist`;
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
export const USER_BLOCKS_API_PATH = `${API_BASE_PATH}/user/blocks`;
export const USER_FREQUENT_EMOJI_API_PATH = `${API_BASE_PATH}/user/frequent-emoji`;
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
// Pages of page spaces (#1179)
export const SPACE_API_PARAM_PAGE = '{pageCuid}';
export const COMMUNITY_SPACE_PAGES_API_PATH = `${COMMUNITY_SPACE_API_PATH}/pages`;
export const COMMUNITY_SPACE_PAGES_ORDER_API_PATH = `${COMMUNITY_SPACE_PAGES_API_PATH}/order`;
export const COMMUNITY_SPACE_PAGE_API_PATH = `${COMMUNITY_SPACE_PAGES_API_PATH}/${SPACE_API_PARAM_PAGE}`;
// Course progress of the current member (#1304)
export const COMMUNITY_SPACE_PROGRESS_API_PATH = `${COMMUNITY_SPACE_API_PATH}/progress`;
export const COMMUNITY_SPACE_PAGE_PROGRESS_API_PATH = `${COMMUNITY_SPACE_PAGE_API_PATH}/progress`;
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
export const COMMUNITY_INVITATIONS_API_PATH = `${COMMUNITY_API_PATH}/invitations`;
export const COMMUNITY_API_PARAM_INVITATION = '{invitationCuid}';
export const COMMUNITY_INVITATION_API_PATH = `${COMMUNITY_INVITATIONS_API_PATH}/${COMMUNITY_API_PARAM_INVITATION}`;
export const COMMUNITY_MEMBERS_PAGE_SIZE = 20;

// Access groups (#1241, ADR 0025). WARNING: aligned with ApiRoutes.php and Constants.php
export const COMMUNITY_ACCESS_GROUPS_API_PATH = `${COMMUNITY_API_PATH}/access-groups`;
export const COMMUNITY_API_PARAM_ACCESS_GROUP = '{accessGroupCuid}';
export const COMMUNITY_ACCESS_GROUP_API_PATH = `${COMMUNITY_ACCESS_GROUPS_API_PATH}/${COMMUNITY_API_PARAM_ACCESS_GROUP}`;
export const COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH = `${COMMUNITY_ACCESS_GROUP_API_PATH}/members`;
export const COMMUNITY_ACCESS_GROUP_MEMBER_API_PATH = `${COMMUNITY_ACCESS_GROUP_MEMBERS_API_PATH}/${COMMUNITY_API_PARAM_MEMBER}`;
export const MIN_LENGTH_ACCESS_GROUP_NAME = 2;
export const MAX_LENGTH_ACCESS_GROUP_NAME = 64;
export const MAX_LENGTH_ACCESS_GROUP_DESCRIPTION = 500;
export const MENTION_SUGGESTIONS_LIMIT = 8;

// Image uploads (#1178)
export const COMMUNITY_UPLOADS_API_PATH = `${COMMUNITY_API_PATH}/uploads`;

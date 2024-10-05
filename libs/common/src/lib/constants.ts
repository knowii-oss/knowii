import { ToastProps } from 'primereact/toast';

/**
 * Meta
 */
export const BUILD_TIME = new Date().toISOString();
export const BUILD_TIMESTAMP = Date.now();

/**
 * Utils
 */
export const IS_PROD = process.env.NODE_ENV === 'production';
export const IS_DEV = process.env.NODE_ENV === 'development';
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * UI/UX
 */
export const MIN_ACTION_TIME = 250;
export const DEFAULT_TOAST_POSITION: ToastProps['position'] = 'top-center';
export const DEFAULT_TEXTAREA_ROWS = 3;
export const USER_PROFILE_BIO_TEXTAREA_ROWS = 8;

/**
 * URLs
 */
export const HOME_URL = '/';
export const DASHBOARD_URL = 'dashboard';
export const CONTACT_URL = 'contact';

// Legal
export const TERMS_OF_SERVICE_URL = 'terms-of-service.show';
export const PRIVACY_POLICY_URL = 'privacy-policy.show';

// User profile
export const USER_PROFILE_URL = 'profile.show';
export const USER_PROFILE_INFORMATION_UPDATE_URL = 'user-profile-information.update';
export const USER_PROFILE_PASSWORD_UPDATE_URL = 'user-password.update';

// Communities
export const COMMUNITY_URL = 'communities.show';

// Resource collections
export const RESOURCE_COLLECTION_URL = 'resource-collections.show';

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
export const PASSWORD_UPDATE_URL = 'password.update';
export const FORGOT_PASSWORD_URL = 'password.request';
// URL for sending email verification requests
export const EMAIL_VERIFICATION_URL = 'verification.send';
export const EMAIL_VERIFICATION_STATUS_LINK_SENT = 'verification-link-sent';
export const DESTROY_OTHER_BROWSER_SESSIONS_URL = 'other-browser-sessions.destroy';
export const DELETE_USER_URL = 'current-user.destroy';

/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
export const PING_API_PATH = `${API_BASE_PATH}/ping`;

// Users
export const USERS_API_BASE_PATH = `${API_BASE_PATH}/users`;
export const USERS_API_IS_USERNAME_AVAILABLE_PATH = `${USERS_API_BASE_PATH}/is-username-available`;

// Communities
export const COMMUNITY_API_BASE_PATH = `${API_BASE_PATH}/communities`;
export const COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH_PARAM_COMMUNITY = '{communityCuid}';
export const COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH = `${API_BASE_PATH}/communities/${COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH_PARAM_COMMUNITY}/resource-collections`;
export const COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_COMMUNITY = '{communityCuid}';
export const COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_RESOURCE_COLLECTION = '{resourceCollectionCuid}';
export const COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH = `${COMMUNITY_RESOURCE_COLLECTION_API_BASE_PATH}/${COMMUNITY_RESOURCE_TEXT_ARTICLES_API_BASE_PATH_PARAM_RESOURCE_COLLECTION}/text-articles`;

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
export const USER_PHONE_REGEX = /^[0-9-+()./ ]+$/;

// Community
export const MIN_LENGTH_COMMUNITY_NAME = 3;
export const MAX_LENGTH_COMMUNITY_NAME = 64;
export const MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
export const ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-@' ]+$/;

// Community resource collection
export const MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME = 3;
export const MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME = 64;
export const MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION = 255;
export const ALLOWED_COMMUNITY_RESOURCE_COLLECTION_NAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-@' ]+$/;

// Community resource
export const MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION = 255;

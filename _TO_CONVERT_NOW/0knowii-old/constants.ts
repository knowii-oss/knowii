export const API_COMMUNITIES_BASE_PATH = '/communities';
export const API_USERS_BASE_PATH = '/users';
export const API_USERNAME_AVAILABILITY_CHECK = `${API_BASE_PATH}${API_USERS_BASE_PATH}/is-username-available`;
export const API_COMMUNITY_NAME_AVAILABILITY_CHECK = `${API_BASE_PATH}${API_COMMUNITIES_BASE_PATH}/is-community-name-available`;
export const API_CREATE_NEW_COMMUNITY = `${API_BASE_PATH}${API_COMMUNITIES_BASE_PATH}/create`;

/**
 * URLs
 */
export const APP_BASE_URL = '/app';
export const ACCOUNT_URL = '/account';
export const COMMUNITY_BASE_URL = '/community';
export const COMMUNITIES_BASE_URL = '/communities';
export const CREATE_COMMUNITY_URL = `${COMMUNITIES_BASE_URL}/create`; // TODO use in UI
export const BLOG_BASE_URL = '/blog';
export const PRIVACY_POLICY_URL = '/privacy';
export const TERMS_OF_USE_URL = '/terms';
export const CONTACT_URL = '/contact';

/**
 * Authentication / Registration
 */
export const AUTH_BASE_PATH = '/auth';

export const SIGN_IN_URL = `${AUTH_BASE_PATH}/${AuthAction.signin}`;
export const SIGN_UP_URL = `${AUTH_BASE_PATH}/${AuthAction.signup}`;
export const FORGOT_PASSWORD_URL = `${AUTH_BASE_PATH}/${AuthAction['forgot-password']}`;
export const RESET_PASSWORD_URL = `${AUTH_BASE_PATH}/${AuthAction['reset-password']}`;
export const CALLBACK_URL = `${AUTH_BASE_PATH}/${AuthAction.callback}`;

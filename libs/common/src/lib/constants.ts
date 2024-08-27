/**
 * Meta
 */
export const BUILD_TIME = new Date().toISOString();
export const BUILD_TIMESTAMP = Date.now();

/**
 * Utils
 */
export const IS_BROWSER = typeof window !== 'undefined';
export const IS_SERVER = typeof window === 'undefined';
export const IS_PROD = process.env.NODE_ENV === 'production';
export const IS_DEV = process.env.NODE_ENV === 'development';
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * URLs
 */
export const HOME_URL = '/';

/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
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

export const AuthAction = {
  signup: 'signup',
  signin: 'signin',
  'forgot-password': 'forgot-password', // FIXME find a way to use normal keys rather than this hack
  'reset-password': 'reset-password',
  callback: 'callback',
} as const;

export type AuthAction = keyof typeof AuthAction;

export const SIGN_IN_URL = `${AUTH_BASE_PATH}/${AuthAction.signin}`;
export const SIGN_UP_URL = `${AUTH_BASE_PATH}/${AuthAction.signup}`;
export const FORGOT_PASSWORD_URL = `${AUTH_BASE_PATH}/${AuthAction['forgot-password']}`;
export const RESET_PASSWORD_URL = `${AUTH_BASE_PATH}/${AuthAction['reset-password']}`;
export const CALLBACK_URL = `${AUTH_BASE_PATH}/${AuthAction.callback}`;

export function isValidAuthAction(action: string): action is AuthAction {
  return Object.values(AuthAction).includes(action as AuthAction);
}

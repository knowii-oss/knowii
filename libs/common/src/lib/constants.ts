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

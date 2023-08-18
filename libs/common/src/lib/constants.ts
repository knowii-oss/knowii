// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthor = require('./metadata.json').author;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorGivenName = require('./metadata.json').authorGivenName;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorFamilyName = require('./metadata.json').authorFamilyName;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorWebsite = require('./metadata.json').social.blogSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorTwitter = require('./metadata.json').social.twitterSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorYouTube = require('./metadata.json').social.youtubeSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorLinkedIn = require('./metadata.json').social.linkedInSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorGitHub = require('./metadata.json').social.githubSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorMedium = require('./metadata.json').social.mediumSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorDevTo = require('./metadata.json').social.devToSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorLinktree = require('./metadata.json').social.linktreeSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorTwitch = require('./metadata.json').social.twitchSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorStackOverflow = require('./metadata.json').social.stackOverflowSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorFacebook = require('./metadata.json').social.facebookSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorHashnode = require('./metadata.json').social.hashnodeSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorReddit = require('./metadata.json').social.redditSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorSubstack = require('./metadata.json').social.substackSebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorRole = require('./metadata.json').roles.sebastien;
// eslint-disable-next-line  @typescript-eslint/no-var-requires
const siteAuthorAvatar = require('./metadata.json').avatars.sebastien;

/**
 * Utils
 */
export const IS_BROWSER = typeof window !== 'undefined';
export const IS_SERVER = typeof window === 'undefined';
export const IS_PROD = process.env.NODE_ENV === 'production';
export const IS_DEV = process.env.NODE_ENV === 'development';
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * Themes
 */
export const THEME_DARK = 'dark';
export const THEME_LIGHT = 'light';

/**
 * API
 */
export const API_BASE_PATH = '/api/v1';
export const API_COMMUNITIES_BASE_PATH = '/communities';
export const API_USERS_BASE_PATH = '/users';
export const API_USERNAME_AVAILABILITY_CHECK = `${API_BASE_PATH}/${API_USERS_BASE_PATH}/is-username-available`;
export const API_COMMUNITY_NAME_AVAILABILITY_CHECK = `${API_BASE_PATH}/${API_COMMUNITIES_BASE_PATH}/is-community-name-available`;
export const API_CREATE_NEW_COMMUNITY = `${API_BASE_PATH}/${API_COMMUNITIES_BASE_PATH}/create`;

/**
 * URLs
 */
export const HOME_URL = '/';
export const APP_BASE_URL = '/app';
export const ACCOUNT_URL = '/account';
export const COMMUNITY_BASE_URL = '/community';
export const COMMUNITIES_BASE_URL = '/communities';
export const CREATE_COMMUNITY_URL = `${COMMUNITIES_BASE_URL}/create`;
export const BLOG_BASE_URL = '/blog';
export const PRIVACY_POLICY_URL = '/privacy';
export const TERMS_OF_USE_URL = '/terms';
export const CONTACT_URL = '/contact';

/**
 * Auth URLs
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

// export function isValidAuthAction(action: string): action is AuthAction {
//   return Object.values(AuthAction).includes(action as AuthAction);
// }

export function isValidAuthAction(action: string): action is AuthAction {
  return Object.values(AuthAction).includes(action as AuthAction);
}

/**
 * Regexes
 * WARNING: Some of those must match the regexes used by PL/SQL functions defined in supabase-db-seed.sql
 */
export const forbiddenUsernameCharactersRegex = /[^a-zA-Z0-9_-]+/g;
// Used with react hook form forms
export const allowedUsernameCharactersRegex = /[a-zA-Z0-9_-]+/g;
export const minLengthUsername = 3;
export const maxLengthUsername = 36;

export const forbiddenCommunityNameCharactersRegex = /[^a-zA-Z0-9 -]+/g;
// Used with react hook form forms
export const allowedCommunityNameCharactersRegex = /[a-zA-Z0-9 -]+/g;

export const forbiddenCommunitySlugCharactersRegex = /[^a-zA-Z0-9-]+/g;
// Used with react hook form forms
export const allowedCommunitySlugCharactersRegex = /[a-zA-Z0-9-]+/g;

export const minLengthCommunityName = 3;
export const maxLengthCommunityName = 64;

export const minLengthCommunitySlug = 3;
export const maxLengthCommunitySlug = 64;

/**
 * Errors
 */

/**
 * An error with basic information
 */
interface ReusableError {
  code: string;
  key: string;
  description: string;
  statusCode: number;
  type: ErrorType;
}

export const errorInternalServerError: ReusableError = {
  code: 'internal_server_error',
  key: 'errors.internalServerError',
  description: 'We have encountered an unexpected issue',
  statusCode: 500,
  type: 'server',
};

export const errorClientNotAuthenticated: ReusableError = {
  code: 'client_not_authenticated',
  key: 'errors.clientNotAuthenticated',
  description: 'The client does not have an active session or is not authenticated',
  statusCode: 401,
  type: 'authentication',
};

export const errorInputValidation: ReusableError = {
  code: 'invalid_request_error',
  key: 'errors.invalidRequestError',
  description: 'The provided request data is incomplete or invalid',
  statusCode: 400,
  type: 'validation',
};

export const errorCommunityNotFound: ReusableError = {
  code: 'community_not_found_error',
  key: 'communityNotFoundError',
  description: 'The community could not be found',
  statusCode: 404,
  type: 'notFound',
};

export const errorCommunityNameNotAvailable: ReusableError = {
  code: 'community_name_not_available',
  key: 'communityNameNotAvailable',
  description: 'The chosen community name is not available',
  statusCode: 409,
  type: 'notAvailable',
};

export const errorCommunitySlugNotAvailable: ReusableError = {
  code: 'community_slug_not_available',
  key: 'communitySlugNotAvailable',
  description: 'The chosen community slug is not available',
  statusCode: 409,
  type: 'notAvailable',
};

/**
 * Possible error types
 */
export const ErrorType = {
  authentication: 'authentication',
  authorization: 'authorization',
  notAvailable: 'notAvailable',
  notFound: 'notFound',
  server: 'server',
  validation: 'validation',
} as const;

export type ErrorType = keyof typeof ErrorType;

/**
 * Meta
 */
export const BUILD_TIME = new Date().toISOString();
export const BUILD_TIMESTAMP = Date.now();

/**
 * Author microdata
 * Reference: https://schema.org/Person
 */
export const SITE_AUTHOR_MICRODATA = {
  '@context': 'https://schema.org/',
  '@type': 'Person',
  name: siteAuthor,
  familyName: siteAuthorFamilyName,
  givenName: siteAuthorGivenName,
  image: siteAuthorAvatar,
  url: siteAuthorWebsite,
  sameAs: [
    siteAuthorTwitter,
    siteAuthorYouTube,
    siteAuthorLinkedIn,
    siteAuthorGitHub,
    siteAuthorMedium,
    siteAuthorDevTo,
    siteAuthorLinktree,
    siteAuthorTwitch,
    siteAuthorStackOverflow,
    siteAuthorFacebook,
    siteAuthorHashnode,
    siteAuthorReddit,
    siteAuthorSubstack,
  ],
  jobTitle: siteAuthorRole,
  worksFor: {
    '@type': 'Organization',
    name: 'DeveloPassion',
    url: 'https://developassion.be',
  },
};

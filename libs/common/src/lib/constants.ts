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
export const IS_TEST = process.env.NODE_ENV === 'test';

/**
 * Themes
 */
export const THEME_DARK = 'dark';
export const THEME_LIGHT = 'light';

/**
 * Auth
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
 */
export const forbiddenUsernameCharactersRegex = /[^a-zA-Z0-9_-]+/;
// Used with react hook form forms
export const allowedUsernameCharactersRegex = /[a-zA-Z0-9_-]+/;
export const minLengthUsername = 3;
export const maxLengthUsername = 36;

export const forbiddenCommunityNameCharactersRegex = /[^a-zA-Z0-9 -]+/;
// Used with react hook form forms
export const allowedCommunityNameCharactersRegex = /[a-zA-Z0-9 -]+/;

export const minLengthCommunityName = 3;
export const maxLengthCommunityName = 64;

/**
 * Errors
 */

/**
 * An error with basic information
 */
interface ReusableError {
  code: string;
  description: string;
}

export const errorInvalidUsername: ReusableError = {
  code: 'invalid_username_provided',
  description: 'The provided username contains forbidden characters',
};

export const errorNoUsernameProvided: ReusableError = {
  code: 'no_username_provided',
  description: 'You must provide a username',
};

export const errorUsernameTooLong: ReusableError = {
  code: 'username_too_long',
  description: 'The provided username is too long',
};

export const errorUsernameTooShort: ReusableError = {
  code: 'username_too_short',
  description: 'The provided username is too short',
};

export const errorNoCommunityNameProvided: ReusableError = {
  code: 'no_community_name_provided',
  description: 'You must provide a community name',
};

export const errorInvalidCommunityName: ReusableError = {
  code: 'invalid_community_name_provided',
  description: 'The provided community name contains forbidden characters',
};

export const errorCommunityNameTooLong: ReusableError = {
  code: 'community_name_too_long',
  description: 'The provided community name is too long',
};

export const errorCommunityNameTooShort: ReusableError = {
  code: 'community_name_too_short',
  description: 'The provided community name is too short',
};

export const errorInternalServerError: ReusableError = {
  code: 'internal_server_error',
  description: 'We have encountered an unexpected issue',
};

export const errorClientNotAuthenticated: ReusableError = {
  code: 'client_not_authenticated',
  description: 'The client does not have an active session or is not authenticated',
};

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

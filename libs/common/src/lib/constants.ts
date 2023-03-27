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
 * Paths
 */
export const BASE_APP_FOLDER = 'apps/knowii';

/**
 * Themes
 */
export const THEME_DARK = 'dark';
export const THEME_LIGHT = 'light';

/**
 * i18n
 */
export const I18N_TRANSLATIONS_ACCOUNT = 'account';
export const I18N_TRANSLATIONS_APP = 'app';
export const I18N_TRANSLATIONS_AUTH = 'auth';
export const I18N_TRANSLATIONS_BLOG = 'blog';
export const I18N_TRANSLATIONS_COMMON = 'common';
export const I18N_TRANSLATIONS_HOME = 'home';

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

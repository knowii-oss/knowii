import { ToastProps } from 'primereact/toast';
import {
  FaCoffee,
  FaDev,
  FaEnvelope,
  FaFacebook,
  FaGithub,
  FaGitlab,
  FaGlobe,
  FaHackerNews,
  FaInstagram,
  FaLinkedin,
  FaMastodon,
  FaMedium,
  FaPatreon,
  FaProductHunt,
  FaReddit,
  FaStore,
  FaTiktok,
  FaTwitch,
  FaYoutube,
} from 'react-icons/fa';
import { IconType } from 'react-icons/lib/iconBase';
import { FaBluesky, FaHashnode, FaThreads, FaXTwitter } from 'react-icons/fa6';
import { BsSubstack } from 'react-icons/bs';

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

/**
 * URLs
 */
export const HOME_URL = '/';
export const DASHBOARD_URL = 'dashboard';
export const CONTACT_URL = 'contact';

// Legal
export const TERMS_OF_SERVICE_URL = 'terms-or-service.show';
export const PRIVACY_POLICY_URL = 'privacy-policy.show';

// User profile
export const USER_PROFILE_URL = 'profile.show';
export const USER_PROFILE_INFORMATION_UPDATE_URL = 'user-profile-information.update';
export const USER_PROFILE_PASSWORD_UPDATE_URL = 'user-password.update';

// Communities
export const COMMUNITY_URL = 'communities.show';

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
export const COMMUNITY_API_BASE_PATH = `${API_BASE_PATH}/communities`;
export const USERS_API_BASE_PATH = `${API_BASE_PATH}/users`;
export const USERS_API_IS_USERNAME_AVAILABLE_PATH = `${USERS_API_BASE_PATH}/is-username-available`;

/**
 * Regexes and other constants
 * WARNING: Those MUST remain aligned with those defined in Constants.php on the back-end
 */
export const MIN_LENGTH_USER_USERNAME = 3;
export const MAX_LENGTH_USER_USERNAME = 36;
export const ALLOWED_USER_USERNAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-']+$/;
export const MIN_LENGTH_SLUG = 3;
export const MAX_LENGTH_SLUG = 128;
export const MIN_LENGTH_COMMUNITY_NAME = 3;
export const MAX_LENGTH_COMMUNITY_NAME = 64;
export const MAX_LENGTH_COMMUNITY_DESCRIPTION = 255;
export const ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX = /^[a-zA-Z0-9-@' ]+$/;

export type SocialMediaLinkProperty =
  | 'social_link_x'
  | 'social_link_website'
  | 'social_link_newsletter'
  | 'social_link_mastodon'
  | 'social_link_bluesky'
  | 'social_link_threads_dot_net'
  | 'social_link_linkedin'
  | 'social_link_facebook'
  | 'social_link_instagram'
  | 'social_link_reddit'
  | 'social_link_medium'
  | 'social_link_substack'
  | 'social_link_hackernews'
  | 'social_link_hashnode'
  | 'social_link_dev_dot_to'
  | 'social_link_youtube'
  | 'social_link_tiktok'
  | 'social_link_twitch'
  | 'social_link_gumroad'
  | 'social_link_buymeacoffee'
  | 'social_link_patreon'
  | 'social_link_producthunt'
  | 'social_link_github'
  | 'social_link_gitlab';

export const SOCIAL_MEDIA_LINK_PROPERTIES: SocialMediaLinkProperty[] = [
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
];

export const SOCIAL_MEDIA_LINK_NAMES: Record<SocialMediaLinkProperty, string> = {
  social_link_x: 'X (Twitter)',
  social_link_website: 'Website',
  social_link_newsletter: 'Newsletter',
  social_link_mastodon: 'Mastodon',
  social_link_bluesky: 'Bluesky',
  social_link_threads_dot_net: 'Threads',
  social_link_linkedin: 'LinkedIn',
  social_link_facebook: 'Facebook',
  social_link_instagram: 'Instagram',
  social_link_reddit: 'Reddit',
  social_link_medium: 'Medium',
  social_link_substack: 'Substack',
  social_link_hackernews: 'Hacker News',
  social_link_hashnode: 'Hashnode',
  social_link_dev_dot_to: 'DEV',
  social_link_youtube: 'YouTube',
  social_link_tiktok: 'TikTok',
  social_link_twitch: 'Twitch',
  social_link_gumroad: 'Gumroad',
  social_link_buymeacoffee: 'Buy Me a Coffee',
  social_link_patreon: 'Patreon',
  social_link_producthunt: 'Product Hunt',
  social_link_github: 'GitHub',
  social_link_gitlab: 'GitLab',
};

export const SOCIAL_MEDIA_LINK_ICONS: Record<SocialMediaLinkProperty, IconType> = {
  social_link_x: FaXTwitter,
  social_link_website: FaGlobe,
  social_link_newsletter: FaEnvelope,
  social_link_mastodon: FaMastodon,
  social_link_bluesky: FaBluesky,
  social_link_threads_dot_net: FaThreads,
  social_link_linkedin: FaLinkedin,
  social_link_facebook: FaFacebook,
  social_link_instagram: FaInstagram,
  social_link_reddit: FaReddit,
  social_link_medium: FaMedium,
  social_link_substack: BsSubstack,
  social_link_hackernews: FaHackerNews,
  social_link_hashnode: FaHashnode,
  social_link_dev_dot_to: FaDev,
  social_link_youtube: FaYoutube,
  social_link_tiktok: FaTiktok,
  social_link_twitch: FaTwitch,
  social_link_gumroad: FaStore,
  social_link_buymeacoffee: FaCoffee,
  social_link_patreon: FaPatreon,
  social_link_producthunt: FaProductHunt,
  social_link_github: FaGithub,
  social_link_gitlab: FaGitlab,
};

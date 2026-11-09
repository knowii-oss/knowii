import { z } from 'zod';
import type { IconType } from 'react-icons';
import { FaBluesky, FaHashnode, FaThreads, FaXTwitter } from 'react-icons/fa6';
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
  FaWikipediaW,
  FaYoutube,
} from 'react-icons/fa';
import { SiGooglescholar, SiKofi, SiOrcid } from 'react-icons/si';
import { BsSubstack } from 'react-icons/bs';

/**
 * The social link properties of a user profile, in display order.
 * WARNING: aligned with Constants::SOCIAL_MEDIA_LINK_PROPERTIES (back-end) and the user_profiles columns
 */
export const SOCIAL_MEDIA_LINK_PROPERTIES = [
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
  'social_link_wikipedia',
  'social_link_google_scholar',
  'social_link_orcid',
  'social_link_kofi',
] as const;

export const socialMediaLinkPropertySchema = z.enum(SOCIAL_MEDIA_LINK_PROPERTIES);

export type SocialMediaLinkProperty = z.infer<typeof socialMediaLinkPropertySchema>;

export const socialMediaLinksSchema = z.object(
  Object.fromEntries(SOCIAL_MEDIA_LINK_PROPERTIES.map((property) => [property, z.string().nullable()])) as Record<
    SocialMediaLinkProperty,
    z.ZodNullable<z.ZodString>
  >,
);

export type SocialMediaLinks = z.infer<typeof socialMediaLinksSchema>;

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
  social_link_wikipedia: 'Wikipedia',
  social_link_google_scholar: 'Google Scholar',
  social_link_orcid: 'ORCID',
  social_link_kofi: 'Ko-fi',
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
  social_link_wikipedia: FaWikipediaW,
  social_link_google_scholar: SiGooglescholar,
  social_link_orcid: SiOrcid,
  social_link_kofi: SiKofi,
};

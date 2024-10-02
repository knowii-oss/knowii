import { z } from 'zod';
import { IconType } from 'react-icons/lib/iconBase';
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
  FaYoutube,
} from 'react-icons/fa';
import { BsSubstack } from 'react-icons/bs';

// FIXME reduce code duplication in this file

export const socialMediaLinksSchema = z.object({
  social_link_x: z.string().nullable(),
  social_link_website: z.string().nullable(),
  social_link_newsletter: z.string().nullable(),
  social_link_mastodon: z.string().nullable(),
  social_link_bluesky: z.string().nullable(),
  social_link_threads_dot_net: z.string().nullable(),
  social_link_linkedin: z.string().nullable(),
  social_link_facebook: z.string().nullable(),
  social_link_instagram: z.string().nullable(),
  social_link_reddit: z.string().nullable(),
  social_link_medium: z.string().nullable(),
  social_link_substack: z.string().nullable(),
  social_link_hackernews: z.string().nullable(),
  social_link_hashnode: z.string().nullable(),
  social_link_dev_dot_to: z.string().nullable(),
  social_link_youtube: z.string().nullable(),
  social_link_tiktok: z.string().nullable(),
  social_link_twitch: z.string().nullable(),
  social_link_gumroad: z.string().nullable(),
  social_link_buymeacoffee: z.string().nullable(),
  social_link_patreon: z.string().nullable(),
  social_link_producthunt: z.string().nullable(),
  social_link_github: z.string().nullable(),
  social_link_gitlab: z.string().nullable(),
});

export type SocialMediaLinks = z.infer<typeof socialMediaLinksSchema>;

export const socialMediaLinkPropertySchema = z.enum([
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
]);

export type SocialMediaLinkProperty = z.infer<typeof socialMediaLinkPropertySchema>;

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

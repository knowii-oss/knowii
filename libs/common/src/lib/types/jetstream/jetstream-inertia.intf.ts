import { DateTime } from '../date-time.intf';
import { Nullable } from '../nullable.intf';
import { Community } from '../community.schema';

export interface CurrentUser {
  cuid: string;
  name: string;
  email: string;
  username: string;
  profile_photo_path: Nullable<string>;
  profile_photo_url: string;
  two_factor_enabled: boolean;
  two_factor_confirmed_at: Nullable<DateTime>;
  email_verified_at: Nullable<DateTime>;

  social_link_x: string | null;
  social_link_website: string | null;
  social_link_newsletter: string | null;
  social_link_mastodon: string | null;
  social_link_bluesky: string | null;
  social_link_threads_dot_net: string | null;
  social_link_linkedin: string | null;
  social_link_facebook: string | null;
  social_link_instagram: string | null;
  social_link_reddit: string | null;
  social_link_medium: string | null;
  social_link_substack: string | null;
  social_link_hackernews: string | null;
  social_link_hashnode: string | null;
  social_link_dev_dot_to: string | null;
  social_link_youtube: string | null;
  social_link_tiktok: string | null;
  social_link_twitch: string | null;
  social_link_gumroad: string | null;
  social_link_buymeacoffee: string | null;
  social_link_patreon: string | null;
  social_link_producthunt: string | null;
  social_link_github: string | null;
  social_link_gitlab: string | null;

  created_at: DateTime;
  updated_at: DateTime;
}

export interface Auth {
  user: Nullable<CurrentUser>;
}

export type InertiaSharedProps<T = NonNullable<unknown>> = T & {
  jetstream: {
    canManageTwoFactorAuthentication: boolean;
    canUpdatePassword: boolean;
    canUpdateProfileInformation: boolean;
    flash: Record<string, string>;
    hasAccountDeletionFeatures: boolean;
    hasApiFeatures: boolean;
    hasTermsAndPrivacyPolicyFeature: boolean;
    managesProfilePhotos: boolean;
    hasEmailVerification: boolean;
  };
  auth: Auth;
  communities: Community[];
  // FIXME improve types
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  errorBags: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  errors: any;
};

export interface Session {
  id: number;
  ip_address: string;
  is_current_device: boolean;
  agent: {
    is_desktop: boolean;
    platform: string;
    browser: string;
  };
  last_active: DateTime;
}

export interface ApiToken {
  id: number;
  name: string;
  abilities: string[];
  last_used_ago: Nullable<DateTime>;
  created_at: DateTime;
  updated_at: DateTime;
}

export interface Role {
  description: string;
  key: string;
  name: string;
  permissions: Permission[];
}

export type Permission = 'create' | 'read' | 'update' | 'delete';

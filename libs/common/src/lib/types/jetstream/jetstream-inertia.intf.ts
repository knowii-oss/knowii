import type { DateTime } from '../date-time.intf';
import type { Nullable } from '../nullable.intf';
import type { CurrentUser, UserProfile } from '../user.schema';

export interface Auth {
  user: Nullable<CurrentUser>;
}

export type InertiaSharedProps<T = NonNullable<unknown>> = T & {
  jetstream: {
    canManageTwoFactorAuthentication: boolean;
    canUpdatePassword: boolean;
    canUpdateProfileInformation: boolean;
    flash: {
      banner?: string;
      bannerStyle?: 'success' | 'danger';
      [key: string]: string | undefined;
    };
    hasAccountDeletionFeatures: boolean;
    hasApiFeatures: boolean;
    hasTermsAndPrivacyPolicyFeature: boolean;
    managesProfilePhotos: boolean;
    hasEmailVerification: boolean;
  };
  auth: Auth;
  // FIXME improve types
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  errorBags: any;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  errors: any;
  userProfile: UserProfile;
  /**
   * The newsletter form of the footer (#938): it shows only when the instance configures a list
   */
  newsletter?: { enabled: boolean; turnstileSiteKey: string | null };
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

// FIXME use or get rid of
export interface Role {
  description: string;
  key: string;
  name: string;
  permissions: Permission[];
}

/**
 * The abilities of API tokens (ADR 0022, #689). WARNING: aligned with JetstreamServiceProvider.php
 */
export const API_TOKEN_PERMISSIONS = [
  'user:read',
  'user:write',
  'communities:read',
  'communities:write',
  'posts:read',
  'posts:write',
  'chat:read',
  'chat:write',
  'pages:read',
  'pages:write',
  'resources:read',
  'resources:write',
  'resources:create',
  'groups:read',
  'groups:write',
] as const;
export type Permission = (typeof API_TOKEN_PERMISSIONS)[number];

export const API_TOKEN_PERMISSION_LABELS: Record<Permission, string> = {
  'user:read': 'Read your account',
  'user:write': 'Change your account (picture)',
  'communities:read': 'Read communities, spaces and members',
  'communities:write': 'Manage communities, spaces and members',
  'posts:read': 'Read posts, comments and reactions',
  'posts:write': 'Write posts, comments and reactions, upload images',
  'chat:read': 'Read chat messages and threads',
  'chat:write': 'Write chat messages and react to them',
  'pages:read': 'Read the pages of page spaces',
  'pages:write': 'Manage the pages of page spaces',
  'resources:read': 'Read resources and their comments',
  'resources:write': 'Add resources, comment and react on them',
  'resources:create': 'Add resources only (browser extension)',
  'groups:read': 'Read access groups and their members',
  'groups:write': 'Manage access groups and their members',
};

/**
 * The lifetimes of a new token, in days, or never (#1267). WARNING: aligned with Constants::API_TOKEN_EXPIRY_CHOICES
 */
export const API_TOKEN_EXPIRY_CHOICES = ['30', '90', '365', 'never'] as const;
export type ApiTokenExpiry = (typeof API_TOKEN_EXPIRY_CHOICES)[number];
export const API_TOKEN_DEFAULT_EXPIRY: ApiTokenExpiry = '90';
export const API_TOKEN_EXPIRY_LABELS: Record<ApiTokenExpiry, string> = {
  '30': '30 days',
  '90': '90 days',
  '365': '1 year',
  never: 'Never',
};

/**
 * A personal access token as Jetstream lists it (API/Index)
 */
export interface ApiToken {
  id: number;
  name: string;
  abilities: string[];
  last_used_ago: string | null;
  created_at: string;
  expires_at: string | null;
}

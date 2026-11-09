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
  'resources:read',
  'resources:write',
  'resources:create',
] as const;
export type Permission = (typeof API_TOKEN_PERMISSIONS)[number];

export const API_TOKEN_PERMISSION_LABELS: Record<Permission, string> = {
  'user:read': 'Read your account',
  'user:write': 'Change your account (picture)',
  'communities:read': 'Read communities, spaces and members',
  'communities:write': 'Manage communities, spaces and members',
  'posts:read': 'Read posts, comments and reactions',
  'posts:write': 'Write posts, comments and reactions, upload images',
  'resources:read': 'Read resources and their comments',
  'resources:write': 'Add resources, comment and react on them',
  'resources:create': 'Add resources only (browser extension)',
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

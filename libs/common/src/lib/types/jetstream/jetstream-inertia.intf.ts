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
  /**
   * The communities of the signed-in member (#1484): the bell names the community of a notification when there are several
   */
  communityCount?: number;
  /**
   * The "Catch up" card (#1476): the date of the last visit when it was 7 days old or more, else null
   */
  catchUp?: { since: string } | null;
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
  /**
   * Sign in with an OpenID Connect provider (#1385): null when the instance has none, so that no page shows a button.
   * `error` and `status` come from the callback, for one request
   */
  oidc?: OpenIdConnectPageProp | null;
};

export interface OpenIdConnectPageProp {
  label: string;
  error: string | null;
  status: string | null;
}

/**
 * How an account proves that it wants to be deleted (#1521, ADR 0052). WARNING: aligned with KnowiiAccountDeletionProof
 * (back-end). `password`: the password. `sign_in`: no password, a new sign-in at the provider of the site. `unavailable`:
 * no password and no way to make the proof (the member writes to the operator of the site).
 */
export type AccountDeletionProof = 'password' | 'sign_in' | 'unavailable';

/**
 * The prop `accountDeletion` of the profile page (#1521)
 */
export interface AccountDeletionState {
  proof: AccountDeletionProof;
  /**
   * The label of the provider of the site (the text of its sign-in button), null without a provider
   */
  label: string | null;
  /**
   * Until when the new sign-in lets the member delete the account (ISO 8601), null without a valid proof
   */
  confirmed_until: string | null;
  /**
   * Whether the deletion asks for the code of two-factor authentication
   */
  two_factor: boolean;
  /**
   * For one request, after the return from the provider: the time until which the proof holds, or the reason of a refusal
   */
  status: string | null;
  error: string | null;
  /**
   * For one request: the choice of the box "Also delete my posts, comments and messages" that went with the proof
   */
  delete_content: boolean | null;
}

/**
 * A provider account that the member connected (#1385): never the identifier at the provider
 */
export interface ConnectedAccount {
  cuid: string;
  issuer: string;
  label: string;
  /**
   * Whether this is the provider of the instance (the one that the sign-in page offers)
   */
  current: boolean;
  linked_at: string;
  last_used_at: string | null;
}

/**
 * What the sign-in provider says that the member owns (#1386, ADR 0047): the keys that were stored at the last read and the
 * date of that read. Only when the instance reads entitlements and the member connected the provider account.
 */
export interface AccountEntitlements {
  keys: string[];
  checked_at: string | null;
}

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
  'account:delete',
  'communities:read',
  'communities:write',
  'posts:read',
  'posts:write',
  'chat:read',
  'chat:write',
  'messages:read',
  'messages:write',
  'notifications:read',
  'notifications:write',
  'pages:read',
  'pages:write',
  'resources:read',
  'resources:write',
  'resources:create',
  'groups:read',
  'groups:write',
  'identities:read',
  'identities:write',
  'entitlements:read',
] as const;
export type Permission = (typeof API_TOKEN_PERMISSIONS)[number];

export const API_TOKEN_PERMISSION_LABELS: Record<Permission, string> = {
  'user:read': 'Read your account',
  'user:write': 'Change your account (picture)',
  'account:delete': 'Delete your account',
  'communities:read': 'Read communities, spaces and members',
  'communities:write': 'Manage communities, spaces and members',
  'posts:read': 'Read posts, comments and reactions',
  'posts:write': 'Write posts, comments and reactions, upload images',
  'chat:read': 'Read chat messages and threads',
  'chat:write': 'Write chat messages and react to them',
  'messages:read': 'Read your direct messages',
  'messages:write': 'Write direct messages and react to them',
  'notifications:read': 'Read your notifications',
  'notifications:write': 'Mark your notifications as seen or read',
  'pages:read': 'Read the pages of page spaces',
  'pages:write': 'Manage the pages of page spaces',
  'resources:read': 'Read resources and their comments',
  'resources:write': 'Add resources, comment and react on them',
  'resources:create': 'Add resources only (browser extension)',
  'groups:read': 'Read access groups and their members',
  'groups:write': 'Manage access groups and their members',
  'identities:read': 'Read the provider accounts that you connected',
  'identities:write': 'Disconnect the provider accounts that you connected',
  'entitlements:read': 'Read what your connected account gives access to',
};

/**
 * One sentence under the label of an ability that needs a warning (#1492). Shown as its own choice on the token screen.
 */
export const API_TOKEN_PERMISSION_DESCRIPTIONS: Partial<Record<Permission, string>> = {
  'account:delete': 'Lets this token delete your account for good, with your password. It cannot be undone.',
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

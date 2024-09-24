import { DateTime } from '../date-time.intf';
import { Nullable } from '../nullable.intf';
import { Community } from '../community.schema';
import { SocialMediaLinkProperty } from '../../constants';

export type CurrentUser = {
  cuid: string;
  name: string;
  email: string;
  username: string;
  profile_photo_path: Nullable<string>;
  profile_photo_url: string;
  two_factor_enabled: boolean;
  two_factor_confirmed_at: Nullable<DateTime>;
  email_verified_at: Nullable<DateTime>;
  created_at: DateTime;
  updated_at: DateTime;
} & { [K in SocialMediaLinkProperty]: string | null };

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

// WARNING MUST remain aligned with permissions defined in JetstreamServiceProvider.php
export type Permission =
  | 'community:create'
  | 'community:read'
  | 'community:update'
  | 'community:delete'
  | 'resource_collection:create'
  | 'resource_collection:read'
  | 'resource_collection:update'
  | 'resource_collection:delete'
  | 'resource:create'
  | 'resource:read'
  | 'resource:update'
  | 'resource:delete';

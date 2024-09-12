import { DateTime } from './date-time.intf';
import { Nullable } from './nullable.intf';

export interface CommunityPermissions {
  canAddCommunityMembers: boolean;
  canDeleteCommunity: boolean;
  canRemoveCommunityMembers: boolean;
  canUpdateCommunity: boolean;
}

// FIXME refactor
export interface CommunityInvitation {
  cuid: string;
  community_id: number;
  email: string;
  role: Nullable<string>;
  created_at: DateTime;
  updated_at: DateTime;
}

export interface Community {
  cuid: string;
  name: string;
  description: string;
  personal_community: boolean;
  created_at: DateTime;
  updated_at: DateTime;
}

export type NewCommunity = Omit<Community, 'cuid' | 'created_at' | 'updated_at'>;

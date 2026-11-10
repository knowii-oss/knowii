import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import {
  ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX,
  MAX_LENGTH_COMMUNITY_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_NAME,
  MIN_LENGTH_COMMUNITY_NAME,
} from '../constants';
import { slugSchema } from './slug.schema';

const communityVisibilitySchema = z.enum(['personal', 'private', 'public'], {
  error: 'Please select the visibility level for your community',
});
export type CommunityVisibility = z.infer<typeof communityVisibilitySchema>;

export const communityVisibilityOptions: Array<{ name: string; visibility: CommunityVisibility; color: string }> = [
  { name: 'Personal', visibility: 'personal', color: 'text-warning' },
  { name: 'Private', visibility: 'private', color: 'text-secondary-text' },
  { name: 'Public', visibility: 'public', color: 'text-success' },
];

/**
 * What each visibility means, for the details page of a community (#1135)
 */
export const COMMUNITY_VISIBILITY_DESCRIPTIONS: Record<CommunityVisibility, string> = {
  personal: 'Only you. A place for your own notes and resources.',
  private: 'Members only. People join with an invitation.',
  public: 'Members only for now. Public spaces come later.',
};

export const newCommunitySchema = z.object({
  // WARNING: those rules must remain aligned with those in CreateCommunity.php
  name: z
    .string()
    .min(MIN_LENGTH_COMMUNITY_NAME, {
      error: `Please choose a name for your new community (between ${MIN_LENGTH_COMMUNITY_NAME} and ${MAX_LENGTH_COMMUNITY_NAME} characters)`,
    })
    .max(MAX_LENGTH_COMMUNITY_NAME, { error: `The name is too long (maximum ${MAX_LENGTH_COMMUNITY_NAME} characters)` })
    .regex(new RegExp(ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX), {
      error: 'Please use only letters, numbers, spaces, hyphens, or arobases',
    }),
  description: z.string().max(MAX_LENGTH_COMMUNITY_DESCRIPTION, {
    error: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_DESCRIPTION} characters)`,
  }),
  visibility: communityVisibilitySchema,
});

export type NewCommunity = z.infer<typeof newCommunitySchema>;

/**
 * The rights that the owner and the admins switch for the moderators of a community (#1240, ADR 0017).
 * WARNING: aligned with KnowiiModeratorPermission (back-end)
 */
export const MODERATOR_PERMISSIONS = ['manage_spaces', 'manage_members', 'pin_posts', 'delete_content'] as const;
export type ModeratorPermission = (typeof MODERATOR_PERMISSIONS)[number];

export const MODERATOR_PERMISSION_LABELS: Record<ModeratorPermission, { name: string; description: string }> = {
  manage_spaces: { name: 'Manage spaces', description: 'Create, edit, move and delete space groups and spaces' },
  manage_members: { name: 'Manage members', description: 'Invite members, change roles below their own, remove members' },
  pin_posts: { name: 'Pin posts', description: 'Pin and unpin posts at the top of a space' },
  delete_content: { name: 'Delete content', description: 'Delete the posts and comments of other members' },
};

export const moderatorPermissionsSchema = z.object({
  manage_spaces: z.boolean(),
  manage_members: z.boolean(),
  pin_posts: z.boolean(),
  delete_content: z.boolean(),
});
export type ModeratorPermissions = z.infer<typeof moderatorPermissionsSchema>;

export const communitySchema = baseEntitySchema
  .extend(newCommunitySchema.shape)
  .extend(slugSchema.shape)
  // member_count: only where the server counts the members (community page, details page, GET community, #1135)
  .extend({
    moderator_permissions: moderatorPermissionsSchema,
    member_count: z.number().int().optional(),
    // Whether the current user belongs to the community (#1187); absent without a signed-in user
    is_member: z.boolean().optional(),
  });

export type Community = z.infer<typeof communitySchema>;

export interface CommunityPermissions {
  // WARNING: The props defined here must remain aligned with the props defined in CommunityController.php
  canManageCommunity: boolean;
  canManageSpaces: boolean;
  canManageModeratorPermissions: boolean;
  canUpdateCommunity: boolean;
  canDeleteCommunity: boolean;

  canAddCommunityMembers: boolean;
  canUpdateCommunityMembers: boolean;
  canRemoveCommunityMembers: boolean;

  // Access groups (#1241)
  canManageAccessGroups: boolean;
  canManageAccessGroupMembers: boolean;
  canViewAccessGroups: boolean;
}

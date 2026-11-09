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

export const communitySchema = baseEntitySchema.extend(newCommunitySchema.shape).extend(slugSchema.shape);

export type Community = z.infer<typeof communitySchema>;

export interface CommunityPermissions {
  // WARNING: The props defined here must remain aligned with the props defined in CommunityController.php
  canManageCommunity: boolean;
  canManageSpaces: boolean;
  canUpdateCommunity: boolean;
  canDeleteCommunity: boolean;

  canAddCommunityMembers: boolean;
  canUpdateCommunityMembers: boolean;
  canRemoveCommunityMembers: boolean;

  canCreateResourceCollection: boolean;
  canUpdateResourceCollection: boolean;
  canDeleteResourceCollection: boolean;

  canCreateResource: boolean;
  canUpdateResource: boolean;
  canDeleteResource: boolean;
}

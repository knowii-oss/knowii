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
  message: 'Please select the visibility level for your community',
});
export type CommunityVisibility = z.infer<typeof communityVisibilitySchema>;

export const communityVisibilityOptions: Array<{ name: string; visibility: CommunityVisibility }> = [
  { name: 'Personal', visibility: 'personal' },
  { name: 'Private', visibility: 'private' },
  { name: 'Public', visibility: 'public' },
];

// Only allow creating Private or Public communities, not personal ones
export const allowedCommunityVisibilityOptionsForCreation: Array<{ name: string; visibility: CommunityVisibility }> = [
  { name: 'Private', visibility: 'private' },
  { name: 'Public', visibility: 'public' },
];

export const newCommunitySchema = z.object({
  // WARNING: those rules must remain aligned with those in CreateCommunity.php
  name: z
    .string()
    .min(MIN_LENGTH_COMMUNITY_NAME, {
      message: `Please choose a name for your new community (between ${MIN_LENGTH_COMMUNITY_NAME} and ${MAX_LENGTH_COMMUNITY_NAME} characters)`,
    })
    .max(MAX_LENGTH_COMMUNITY_NAME, { message: `The name is too long (maximum ${MAX_LENGTH_COMMUNITY_NAME} characters)` })
    .regex(new RegExp(ALLOWED_COMMUNITY_NAME_CHARACTERS_REGEX), {
      message: 'Please use only letters, numbers, spaces, hyphens, or arobases',
    }),
  description: z.string().max(MAX_LENGTH_COMMUNITY_DESCRIPTION, {
    message: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_DESCRIPTION} characters)`,
  }),
  visibility: communityVisibilitySchema,
});

export type NewCommunity = z.infer<typeof newCommunitySchema>;

export const communitySchema = baseEntitySchema.merge(newCommunitySchema).merge(slugSchema);

export type Community = z.infer<typeof communitySchema>;

export interface CommunityPermissions {
  // WARNING: The props defined here must remain aligned with the props defined in CommunityController.php
  canUpdateCommunity: boolean;
  canDeleteCommunity: boolean;

  canAddCommunityMembers: boolean;
  canUpdateCommunityMembers: boolean;
  canRemoveCommunityMembers: boolean;

  canCreateResourceCollections: boolean;
  canUpdateResourceCollections: boolean;
  canDeleteResourceCollections: boolean;
}

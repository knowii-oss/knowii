import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';

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

  // TODO add validation constraints
  // Reference: https://github.com/knowii-oss/knowii/blob/588760bb5aee7328d35be597a1656ba983ba43f1/apps/knowii/pages/communities/create/index.tsx
  // add regex pattern
  name: z
    .string()
    .min(3, { message: 'Please choose a name for your new community (between 3 and 64 characters' })
    .max(64, { message: 'The name is too long (maximum 64 characters)' }),
  description: z.string().max(255, { message: 'Your description is too long (maximum 255 characters)' }),
  visibility: communityVisibilitySchema,
});

export type NewCommunity = z.infer<typeof newCommunitySchema>;

export const communitySchema = baseEntitySchema.merge(newCommunitySchema).extend({
  slug: z.string(),
});

export type Community = z.infer<typeof communitySchema>;

export interface CommunityPermissions {
  canAddCommunityMembers: boolean;
  canDeleteCommunity: boolean;
  canRemoveCommunityMembers: boolean;
  canUpdateCommunity: boolean;
  canUpdateCommunityMembers: boolean;
}

// TODO implement
//
// export interface CommunityInvitation {
//   cuid: string;
//   community_id: number;
//   email: string;
//   role: Nullable<string>;
//   created_at: DateTime;
//   updated_at: DateTime;
// }

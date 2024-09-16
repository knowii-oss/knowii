import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';

const communityVisibilitySchema = z.enum(['personal', 'private', 'public']);
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
  // FIXME align length constraint between backend and frontend
  name: z.string().min(3, { message: 'Too short' }).max(64, { message: 'Too long' }),
  description: z.string(),
  visibility: communityVisibilitySchema,
  // TODO add slug
  // slug: z
  //   .string()
  //   .min(3)
  //   .max(64)
  //   .regex(/^[a-z0-9-]+$/gim),
});

export type NewCommunity = z.infer<typeof newCommunitySchema>;

export const communitySchema = baseEntitySchema.merge(newCommunitySchema);

export type Community = z.infer<typeof communitySchema>;

// FIXME extract those types
// export interface CommunityPermissions {
//   canAddCommunityMembers: boolean;
//   canDeleteCommunity: boolean;
//   canRemoveCommunityMembers: boolean;
//   canUpdateCommunity: boolean;
// }
//
// export interface CommunityInvitation {
//   cuid: string;
//   community_id: number;
//   email: string;
//   role: Nullable<string>;
//   created_at: DateTime;
//   updated_at: DateTime;
// }

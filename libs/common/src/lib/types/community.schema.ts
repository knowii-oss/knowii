import { z } from 'zod';

export const communitySchema = z.object({
  // FIXME align length constraint between backend and frontend
  name: z.string().min(3).max(64),
  description: z.string(),
  cuid: z.string(),
  personal: z.boolean(),
  // TODO add slug
  // slug: z
  //   .string()
  //   .min(3)
  //   .max(64)
  //   .regex(/^[a-z0-9-]+$/gim),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Community = z.infer<typeof communitySchema>;

export type NewCommunity = Omit<Community, 'cuid' | 'created_at' | 'updated_at'>;

// TODO extract those types
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

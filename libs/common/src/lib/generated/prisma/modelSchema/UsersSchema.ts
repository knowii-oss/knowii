import { z } from 'zod';
import { KnowiiUserRoleSchema } from '../inputTypeSchemas/KnowiiUserRoleSchema';
import type { CommunitiesWithRelations } from './CommunitiesSchema';
import type { CommunitiesPartialWithRelations } from './CommunitiesSchema';
import type { CommunitiesOptionalDefaultsWithRelations } from './CommunitiesSchema';
import type { UserProfilesWithRelations } from './UserProfilesSchema';
import type { UserProfilesPartialWithRelations } from './UserProfilesSchema';
import type { UserProfilesOptionalDefaultsWithRelations } from './UserProfilesSchema';
import { CommunitiesWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesPartialWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesOptionalDefaultsWithRelationsSchema } from './CommunitiesSchema';
import { UserProfilesWithRelationsSchema } from './UserProfilesSchema';
import { UserProfilesPartialWithRelationsSchema } from './UserProfilesSchema';
import { UserProfilesOptionalDefaultsWithRelationsSchema } from './UserProfilesSchema';

/////////////////////////////////////////
// USERS SCHEMA
/////////////////////////////////////////

export const UsersSchema = z.object({
  user_role: KnowiiUserRoleSchema,
  /**
   * Internal user id (referenced in other tables)
   */
  id: z.string().uuid(),
  /**
   * Same id as the id in auth.users (ie Supabase user id)
   * Can be null because users can delete their account but we keep the entry anyway
   */
  user_id_external: z.string().uuid().nullable(),
  /**
   * WARNING: The Zod validation rules MUST respect the same length rules as the UI & back-end (cfr constants.ts)
   */
  username: z.string().min(3).max(36),
  email: z.string().email(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Users = z.infer<typeof UsersSchema>;

/////////////////////////////////////////
// USERS PARTIAL SCHEMA
/////////////////////////////////////////

export const UsersPartialSchema = UsersSchema.partial();

export type UsersPartial = z.infer<typeof UsersPartialSchema>;

/////////////////////////////////////////
// USERS OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const UsersOptionalDefaultsSchema = UsersSchema.merge(
  z.object({
    user_role: KnowiiUserRoleSchema.optional(),
    /**
     * Internal user id (referenced in other tables)
     */
    id: z.string().uuid().optional(),
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type UsersOptionalDefaults = z.infer<typeof UsersOptionalDefaultsSchema>;

/////////////////////////////////////////
// USERS RELATION SCHEMA
/////////////////////////////////////////

export type UsersRelations = {
  admin_of: CommunitiesWithRelations[];
  member_of: CommunitiesWithRelations[];
  owner_of: CommunitiesWithRelations[];
  user_profile?: UserProfilesWithRelations | null;
  user_profile_external?: UserProfilesWithRelations | null;
};

export type UsersWithRelations = z.infer<typeof UsersSchema> & UsersRelations;

export const UsersWithRelationsSchema: z.ZodType<UsersWithRelations> = UsersSchema.merge(
  z.object({
    admin_of: z.lazy(() => CommunitiesWithRelationsSchema).array(),
    member_of: z.lazy(() => CommunitiesWithRelationsSchema).array(),
    owner_of: z.lazy(() => CommunitiesWithRelationsSchema).array(),
    user_profile: z.lazy(() => UserProfilesWithRelationsSchema).nullable(),
    user_profile_external: z.lazy(() => UserProfilesWithRelationsSchema).nullable(),
  }),
);

/////////////////////////////////////////
// USERS OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type UsersOptionalDefaultsRelations = {
  admin_of: CommunitiesOptionalDefaultsWithRelations[];
  member_of: CommunitiesOptionalDefaultsWithRelations[];
  owner_of: CommunitiesOptionalDefaultsWithRelations[];
  user_profile?: UserProfilesOptionalDefaultsWithRelations | null;
  user_profile_external?: UserProfilesOptionalDefaultsWithRelations | null;
};

export type UsersOptionalDefaultsWithRelations = z.infer<typeof UsersOptionalDefaultsSchema> & UsersOptionalDefaultsRelations;

export const UsersOptionalDefaultsWithRelationsSchema: z.ZodType<UsersOptionalDefaultsWithRelations> = UsersOptionalDefaultsSchema.merge(
  z.object({
    admin_of: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema).array(),
    member_of: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema).array(),
    owner_of: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema).array(),
    user_profile: z.lazy(() => UserProfilesOptionalDefaultsWithRelationsSchema).nullable(),
    user_profile_external: z.lazy(() => UserProfilesOptionalDefaultsWithRelationsSchema).nullable(),
  }),
);

/////////////////////////////////////////
// USERS PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type UsersPartialRelations = {
  admin_of?: CommunitiesPartialWithRelations[];
  member_of?: CommunitiesPartialWithRelations[];
  owner_of?: CommunitiesPartialWithRelations[];
  user_profile?: UserProfilesPartialWithRelations | null;
  user_profile_external?: UserProfilesPartialWithRelations | null;
};

export type UsersPartialWithRelations = z.infer<typeof UsersPartialSchema> & UsersPartialRelations;

export const UsersPartialWithRelationsSchema: z.ZodType<UsersPartialWithRelations> = UsersPartialSchema.merge(
  z.object({
    admin_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
    member_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
    owner_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
    user_profile: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
    user_profile_external: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
  }),
).partial();

export type UsersOptionalDefaultsWithPartialRelations = z.infer<typeof UsersOptionalDefaultsSchema> & UsersPartialRelations;

export const UsersOptionalDefaultsWithPartialRelationsSchema: z.ZodType<UsersOptionalDefaultsWithPartialRelations> =
  UsersOptionalDefaultsSchema.merge(
    z
      .object({
        admin_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
        member_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
        owner_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
        user_profile: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
        user_profile_external: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
      })
      .partial(),
  );

export type UsersWithPartialRelations = z.infer<typeof UsersSchema> & UsersPartialRelations;

export const UsersWithPartialRelationsSchema: z.ZodType<UsersWithPartialRelations> = UsersSchema.merge(
  z
    .object({
      admin_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
      member_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
      owner_of: z.lazy(() => CommunitiesPartialWithRelationsSchema).array(),
      user_profile: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
      user_profile_external: z.lazy(() => UserProfilesPartialWithRelationsSchema).nullable(),
    })
    .partial(),
);

export default UsersSchema;

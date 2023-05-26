import { z } from 'zod';
import type { UsersWithRelations } from './UsersSchema';
import type { UsersPartialWithRelations } from './UsersSchema';
import type { UsersOptionalDefaultsWithRelations } from './UsersSchema';
import { UsersWithRelationsSchema } from './UsersSchema';
import { UsersPartialWithRelationsSchema } from './UsersSchema';
import { UsersOptionalDefaultsWithRelationsSchema } from './UsersSchema';

/////////////////////////////////////////
// USER PROFILES SCHEMA
/////////////////////////////////////////

export const UserProfilesSchema = z.object({
  id: z.string().uuid(),
  /**
   * Internal user id
   */
  user_id: z.string().uuid(),
  /**
   * Supabase user id
   */
  user_id_external: z.string().uuid().nullable(),
  name: z.string(),
  avatar_url: z.string(),
  phone: z.string(),
  website: z.string(),
  twitter: z.string(),
  facebook: z.string(),
  instagram: z.string(),
  tiktok: z.string(),
  github: z.string(),
  bio: z.string(),
  location: z.string(),
  organization_name: z.string(),
  organization_link: z.string(),
  /**
   * WARNING: When adding/updating/removing fields here, make sure to update the triggers in supabase-db-seed.sql
   */
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type UserProfiles = z.infer<typeof UserProfilesSchema>;

/////////////////////////////////////////
// USER PROFILES PARTIAL SCHEMA
/////////////////////////////////////////

export const UserProfilesPartialSchema = UserProfilesSchema.partial();

export type UserProfilesPartial = z.infer<typeof UserProfilesPartialSchema>;

/////////////////////////////////////////
// USER PROFILES OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const UserProfilesOptionalDefaultsSchema = UserProfilesSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
    name: z.string().optional(),
    avatar_url: z.string().optional(),
    phone: z.string().optional(),
    website: z.string().optional(),
    twitter: z.string().optional(),
    facebook: z.string().optional(),
    instagram: z.string().optional(),
    tiktok: z.string().optional(),
    github: z.string().optional(),
    bio: z.string().optional(),
    location: z.string().optional(),
    organization_name: z.string().optional(),
    organization_link: z.string().optional(),
    /**
     * WARNING: When adding/updating/removing fields here, make sure to update the triggers in supabase-db-seed.sql
     */
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type UserProfilesOptionalDefaults = z.infer<typeof UserProfilesOptionalDefaultsSchema>;

/////////////////////////////////////////
// USER PROFILES RELATION SCHEMA
/////////////////////////////////////////

export type UserProfilesRelations = {
  user?: UsersWithRelations | null;
  user_external?: UsersWithRelations | null;
};

export type UserProfilesWithRelations = z.infer<typeof UserProfilesSchema> & UserProfilesRelations;

export const UserProfilesWithRelationsSchema: z.ZodType<UserProfilesWithRelations> = UserProfilesSchema.merge(
  z.object({
    user: z.lazy(() => UsersWithRelationsSchema).nullable(),
    user_external: z.lazy(() => UsersWithRelationsSchema).nullable(),
  }),
);

/////////////////////////////////////////
// USER PROFILES OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type UserProfilesOptionalDefaultsRelations = {
  user?: UsersOptionalDefaultsWithRelations | null;
  user_external?: UsersOptionalDefaultsWithRelations | null;
};

export type UserProfilesOptionalDefaultsWithRelations = z.infer<typeof UserProfilesOptionalDefaultsSchema> &
  UserProfilesOptionalDefaultsRelations;

export const UserProfilesOptionalDefaultsWithRelationsSchema: z.ZodType<UserProfilesOptionalDefaultsWithRelations> =
  UserProfilesOptionalDefaultsSchema.merge(
    z.object({
      user: z.lazy(() => UsersOptionalDefaultsWithRelationsSchema).nullable(),
      user_external: z.lazy(() => UsersOptionalDefaultsWithRelationsSchema).nullable(),
    }),
  );

/////////////////////////////////////////
// USER PROFILES PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type UserProfilesPartialRelations = {
  user?: UsersPartialWithRelations | null;
  user_external?: UsersPartialWithRelations | null;
};

export type UserProfilesPartialWithRelations = z.infer<typeof UserProfilesPartialSchema> & UserProfilesPartialRelations;

export const UserProfilesPartialWithRelationsSchema: z.ZodType<UserProfilesPartialWithRelations> = UserProfilesPartialSchema.merge(
  z.object({
    user: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
    user_external: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
  }),
).partial();

export type UserProfilesOptionalDefaultsWithPartialRelations = z.infer<typeof UserProfilesOptionalDefaultsSchema> &
  UserProfilesPartialRelations;

export const UserProfilesOptionalDefaultsWithPartialRelationsSchema: z.ZodType<UserProfilesOptionalDefaultsWithPartialRelations> =
  UserProfilesOptionalDefaultsSchema.merge(
    z
      .object({
        user: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
        user_external: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
      })
      .partial(),
  );

export type UserProfilesWithPartialRelations = z.infer<typeof UserProfilesSchema> & UserProfilesPartialRelations;

export const UserProfilesWithPartialRelationsSchema: z.ZodType<UserProfilesWithPartialRelations> = UserProfilesSchema.merge(
  z
    .object({
      user: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
      user_external: z.lazy(() => UsersPartialWithRelationsSchema).nullable(),
    })
    .partial(),
);

export default UserProfilesSchema;

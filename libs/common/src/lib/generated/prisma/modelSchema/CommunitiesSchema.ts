import { z } from 'zod';
import { KnowiiCommunityVisibilitySchema } from '../inputTypeSchemas/KnowiiCommunityVisibilitySchema';
import type { UsersWithRelations } from './UsersSchema';
import type { UsersPartialWithRelations } from './UsersSchema';
import type { UsersOptionalDefaultsWithRelations } from './UsersSchema';
import type { ResourceCollectionsWithRelations } from './ResourceCollectionsSchema';
import type { ResourceCollectionsPartialWithRelations } from './ResourceCollectionsSchema';
import type { ResourceCollectionsOptionalDefaultsWithRelations } from './ResourceCollectionsSchema';
import type { ResourcesWithRelations } from './ResourcesSchema';
import type { ResourcesPartialWithRelations } from './ResourcesSchema';
import type { ResourcesOptionalDefaultsWithRelations } from './ResourcesSchema';
import type { TagsWithRelations } from './TagsSchema';
import type { TagsPartialWithRelations } from './TagsSchema';
import type { TagsOptionalDefaultsWithRelations } from './TagsSchema';
import { UsersWithRelationsSchema } from './UsersSchema';
import { UsersPartialWithRelationsSchema } from './UsersSchema';
import { UsersOptionalDefaultsWithRelationsSchema } from './UsersSchema';
import { ResourceCollectionsWithRelationsSchema } from './ResourceCollectionsSchema';
import { ResourceCollectionsPartialWithRelationsSchema } from './ResourceCollectionsSchema';
import { ResourceCollectionsOptionalDefaultsWithRelationsSchema } from './ResourceCollectionsSchema';
import { ResourcesWithRelationsSchema } from './ResourcesSchema';
import { ResourcesPartialWithRelationsSchema } from './ResourcesSchema';
import { ResourcesOptionalDefaultsWithRelationsSchema } from './ResourcesSchema';
import { TagsWithRelationsSchema } from './TagsSchema';
import { TagsPartialWithRelationsSchema } from './TagsSchema';
import { TagsOptionalDefaultsWithRelationsSchema } from './TagsSchema';

/////////////////////////////////////////
// COMMUNITIES SCHEMA
/////////////////////////////////////////

export const CommunitiesSchema = z.object({
  visibility: KnowiiCommunityVisibilitySchema,
  id: z.string().uuid(),
  /**
   * WARNING: The Zod validation rules MUST respect the same length rules as the UI & back-end (cfr constants.ts)
   */
  name: z.string().min(3).max(64),
  slug: z
    .string()
    .min(3)
    .max(64)
    .regex(/^[a-z0-9-]+$/gim),
  description: z.string(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Communities = z.infer<typeof CommunitiesSchema>;

/////////////////////////////////////////
// COMMUNITIES PARTIAL SCHEMA
/////////////////////////////////////////

export const CommunitiesPartialSchema = CommunitiesSchema.partial();

export type CommunitiesPartial = z.infer<typeof CommunitiesPartialSchema>;

/////////////////////////////////////////
// COMMUNITIES OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const CommunitiesOptionalDefaultsSchema = CommunitiesSchema.merge(
  z.object({
    visibility: KnowiiCommunityVisibilitySchema.optional(),
    id: z.string().uuid().optional(),
    description: z.string().optional(),
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type CommunitiesOptionalDefaults = z.infer<typeof CommunitiesOptionalDefaultsSchema>;

/////////////////////////////////////////
// COMMUNITIES RELATION SCHEMA
/////////////////////////////////////////

export type CommunitiesRelations = {
  admins: UsersWithRelations[];
  members: UsersWithRelations[];
  owners: UsersWithRelations[];
  resource_collections: ResourceCollectionsWithRelations[];
  resources: ResourcesWithRelations[];
  tags: TagsWithRelations[];
};

export type CommunitiesWithRelations = z.infer<typeof CommunitiesSchema> & CommunitiesRelations;

export const CommunitiesWithRelationsSchema: z.ZodType<CommunitiesWithRelations> = CommunitiesSchema.merge(
  z.object({
    admins: z.lazy(() => UsersWithRelationsSchema).array(),
    members: z.lazy(() => UsersWithRelationsSchema).array(),
    owners: z.lazy(() => UsersWithRelationsSchema).array(),
    resource_collections: z.lazy(() => ResourceCollectionsWithRelationsSchema).array(),
    resources: z.lazy(() => ResourcesWithRelationsSchema).array(),
    tags: z.lazy(() => TagsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// COMMUNITIES OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type CommunitiesOptionalDefaultsRelations = {
  admins: UsersOptionalDefaultsWithRelations[];
  members: UsersOptionalDefaultsWithRelations[];
  owners: UsersOptionalDefaultsWithRelations[];
  resource_collections: ResourceCollectionsOptionalDefaultsWithRelations[];
  resources: ResourcesOptionalDefaultsWithRelations[];
  tags: TagsOptionalDefaultsWithRelations[];
};

export type CommunitiesOptionalDefaultsWithRelations = z.infer<typeof CommunitiesOptionalDefaultsSchema> &
  CommunitiesOptionalDefaultsRelations;

export const CommunitiesOptionalDefaultsWithRelationsSchema: z.ZodType<CommunitiesOptionalDefaultsWithRelations> =
  CommunitiesOptionalDefaultsSchema.merge(
    z.object({
      admins: z.lazy(() => UsersOptionalDefaultsWithRelationsSchema).array(),
      members: z.lazy(() => UsersOptionalDefaultsWithRelationsSchema).array(),
      owners: z.lazy(() => UsersOptionalDefaultsWithRelationsSchema).array(),
      resource_collections: z.lazy(() => ResourceCollectionsOptionalDefaultsWithRelationsSchema).array(),
      resources: z.lazy(() => ResourcesOptionalDefaultsWithRelationsSchema).array(),
      tags: z.lazy(() => TagsOptionalDefaultsWithRelationsSchema).array(),
    }),
  );

/////////////////////////////////////////
// COMMUNITIES PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type CommunitiesPartialRelations = {
  admins?: UsersPartialWithRelations[];
  members?: UsersPartialWithRelations[];
  owners?: UsersPartialWithRelations[];
  resource_collections?: ResourceCollectionsPartialWithRelations[];
  resources?: ResourcesPartialWithRelations[];
  tags?: TagsPartialWithRelations[];
};

export type CommunitiesPartialWithRelations = z.infer<typeof CommunitiesPartialSchema> & CommunitiesPartialRelations;

export const CommunitiesPartialWithRelationsSchema: z.ZodType<CommunitiesPartialWithRelations> = CommunitiesPartialSchema.merge(
  z.object({
    admins: z.lazy(() => UsersPartialWithRelationsSchema).array(),
    members: z.lazy(() => UsersPartialWithRelationsSchema).array(),
    owners: z.lazy(() => UsersPartialWithRelationsSchema).array(),
    resource_collections: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema).array(),
    resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
    tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
  }),
).partial();

export type CommunitiesOptionalDefaultsWithPartialRelations = z.infer<typeof CommunitiesOptionalDefaultsSchema> &
  CommunitiesPartialRelations;

export const CommunitiesOptionalDefaultsWithPartialRelationsSchema: z.ZodType<CommunitiesOptionalDefaultsWithPartialRelations> =
  CommunitiesOptionalDefaultsSchema.merge(
    z
      .object({
        admins: z.lazy(() => UsersPartialWithRelationsSchema).array(),
        members: z.lazy(() => UsersPartialWithRelationsSchema).array(),
        owners: z.lazy(() => UsersPartialWithRelationsSchema).array(),
        resource_collections: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema).array(),
        resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
        tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type CommunitiesWithPartialRelations = z.infer<typeof CommunitiesSchema> & CommunitiesPartialRelations;

export const CommunitiesWithPartialRelationsSchema: z.ZodType<CommunitiesWithPartialRelations> = CommunitiesSchema.merge(
  z
    .object({
      admins: z.lazy(() => UsersPartialWithRelationsSchema).array(),
      members: z.lazy(() => UsersPartialWithRelationsSchema).array(),
      owners: z.lazy(() => UsersPartialWithRelationsSchema).array(),
      resource_collections: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema).array(),
      resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
      tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
    })
    .partial(),
);

export default CommunitiesSchema;

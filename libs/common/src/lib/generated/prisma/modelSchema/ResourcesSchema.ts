import { z } from 'zod';
import type { ResourceCollectionsWithRelations } from './ResourceCollectionsSchema';
import type { ResourceCollectionsPartialWithRelations } from './ResourceCollectionsSchema';
import type { ResourceCollectionsOptionalDefaultsWithRelations } from './ResourceCollectionsSchema';
import type { CommunitiesWithRelations } from './CommunitiesSchema';
import type { CommunitiesPartialWithRelations } from './CommunitiesSchema';
import type { CommunitiesOptionalDefaultsWithRelations } from './CommunitiesSchema';
import type { TagsWithRelations } from './TagsSchema';
import type { TagsPartialWithRelations } from './TagsSchema';
import type { TagsOptionalDefaultsWithRelations } from './TagsSchema';
import { ResourceCollectionsWithRelationsSchema } from './ResourceCollectionsSchema';
import { ResourceCollectionsPartialWithRelationsSchema } from './ResourceCollectionsSchema';
import { ResourceCollectionsOptionalDefaultsWithRelationsSchema } from './ResourceCollectionsSchema';
import { CommunitiesWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesPartialWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesOptionalDefaultsWithRelationsSchema } from './CommunitiesSchema';
import { TagsWithRelationsSchema } from './TagsSchema';
import { TagsPartialWithRelationsSchema } from './TagsSchema';
import { TagsOptionalDefaultsWithRelationsSchema } from './TagsSchema';

/////////////////////////////////////////
// RESOURCES SCHEMA
/////////////////////////////////////////

export const ResourcesSchema = z.object({
  id: z.string().uuid(),
  name: z.string(),
  description: z.string(),
  content: z.string(),
  source: z.string().url(),
  slug: z
    .string()
    .min(3)
    .max(64)
    .regex(/^[a-z0-9-]+$/gim),
  up_votes: z.number(),
  down_votes: z.number(),
  community_id: z.string().uuid(),
  resource_collection_id: z.string().uuid(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Resources = z.infer<typeof ResourcesSchema>;

/////////////////////////////////////////
// RESOURCES PARTIAL SCHEMA
/////////////////////////////////////////

export const ResourcesPartialSchema = ResourcesSchema.partial();

export type ResourcesPartial = z.infer<typeof ResourcesPartialSchema>;

/////////////////////////////////////////
// RESOURCES OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const ResourcesOptionalDefaultsSchema = ResourcesSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
    name: z.string().optional(),
    description: z.string().optional(),
    content: z.string().optional(),
    source: z.string().url().optional(),
    up_votes: z.number().optional(),
    down_votes: z.number().optional(),
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type ResourcesOptionalDefaults = z.infer<typeof ResourcesOptionalDefaultsSchema>;

/////////////////////////////////////////
// RESOURCES RELATION SCHEMA
/////////////////////////////////////////

export type ResourcesRelations = {
  resource_collection: ResourceCollectionsWithRelations;
  community: CommunitiesWithRelations;
  tags: TagsWithRelations[];
};

export type ResourcesWithRelations = z.infer<typeof ResourcesSchema> & ResourcesRelations;

export const ResourcesWithRelationsSchema: z.ZodType<ResourcesWithRelations> = ResourcesSchema.merge(
  z.object({
    resource_collection: z.lazy(() => ResourceCollectionsWithRelationsSchema),
    community: z.lazy(() => CommunitiesWithRelationsSchema),
    tags: z.lazy(() => TagsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// RESOURCES OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type ResourcesOptionalDefaultsRelations = {
  resource_collection: ResourceCollectionsOptionalDefaultsWithRelations;
  community: CommunitiesOptionalDefaultsWithRelations;
  tags: TagsOptionalDefaultsWithRelations[];
};

export type ResourcesOptionalDefaultsWithRelations = z.infer<typeof ResourcesOptionalDefaultsSchema> & ResourcesOptionalDefaultsRelations;

export const ResourcesOptionalDefaultsWithRelationsSchema: z.ZodType<ResourcesOptionalDefaultsWithRelations> =
  ResourcesOptionalDefaultsSchema.merge(
    z.object({
      resource_collection: z.lazy(() => ResourceCollectionsOptionalDefaultsWithRelationsSchema),
      community: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema),
      tags: z.lazy(() => TagsOptionalDefaultsWithRelationsSchema).array(),
    }),
  );

/////////////////////////////////////////
// RESOURCES PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type ResourcesPartialRelations = {
  resource_collection?: ResourceCollectionsPartialWithRelations;
  community?: CommunitiesPartialWithRelations;
  tags?: TagsPartialWithRelations[];
};

export type ResourcesPartialWithRelations = z.infer<typeof ResourcesPartialSchema> & ResourcesPartialRelations;

export const ResourcesPartialWithRelationsSchema: z.ZodType<ResourcesPartialWithRelations> = ResourcesPartialSchema.merge(
  z.object({
    resource_collection: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema),
    community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
    tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
  }),
).partial();

export type ResourcesOptionalDefaultsWithPartialRelations = z.infer<typeof ResourcesOptionalDefaultsSchema> & ResourcesPartialRelations;

export const ResourcesOptionalDefaultsWithPartialRelationsSchema: z.ZodType<ResourcesOptionalDefaultsWithPartialRelations> =
  ResourcesOptionalDefaultsSchema.merge(
    z
      .object({
        resource_collection: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema),
        community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
        tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type ResourcesWithPartialRelations = z.infer<typeof ResourcesSchema> & ResourcesPartialRelations;

export const ResourcesWithPartialRelationsSchema: z.ZodType<ResourcesWithPartialRelations> = ResourcesSchema.merge(
  z
    .object({
      resource_collection: z.lazy(() => ResourceCollectionsPartialWithRelationsSchema),
      community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
      tags: z.lazy(() => TagsPartialWithRelationsSchema).array(),
    })
    .partial(),
);

export default ResourcesSchema;

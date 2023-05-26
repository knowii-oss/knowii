import { z } from 'zod';
import type { CommunitiesWithRelations } from './CommunitiesSchema';
import type { CommunitiesPartialWithRelations } from './CommunitiesSchema';
import type { CommunitiesOptionalDefaultsWithRelations } from './CommunitiesSchema';
import type { ResourcesWithRelations } from './ResourcesSchema';
import type { ResourcesPartialWithRelations } from './ResourcesSchema';
import type { ResourcesOptionalDefaultsWithRelations } from './ResourcesSchema';
import { CommunitiesWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesPartialWithRelationsSchema } from './CommunitiesSchema';
import { CommunitiesOptionalDefaultsWithRelationsSchema } from './CommunitiesSchema';
import { ResourcesWithRelationsSchema } from './ResourcesSchema';
import { ResourcesPartialWithRelationsSchema } from './ResourcesSchema';
import { ResourcesOptionalDefaultsWithRelationsSchema } from './ResourcesSchema';

/////////////////////////////////////////
// RESOURCE COLLECTIONS SCHEMA
/////////////////////////////////////////

export const ResourceCollectionsSchema = z.object({
  id: z.string().uuid(),
  name: z.string(),
  slug: z.string(),
  description: z.string(),
  community_id: z.string().uuid(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type ResourceCollections = z.infer<typeof ResourceCollectionsSchema>;

/////////////////////////////////////////
// RESOURCE COLLECTIONS PARTIAL SCHEMA
/////////////////////////////////////////

export const ResourceCollectionsPartialSchema = ResourceCollectionsSchema.partial();

export type ResourceCollectionsPartial = z.infer<typeof ResourceCollectionsPartialSchema>;

/////////////////////////////////////////
// RESOURCE COLLECTIONS OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const ResourceCollectionsOptionalDefaultsSchema = ResourceCollectionsSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
    name: z.string().optional(),
    description: z.string().optional(),
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type ResourceCollectionsOptionalDefaults = z.infer<typeof ResourceCollectionsOptionalDefaultsSchema>;

/////////////////////////////////////////
// RESOURCE COLLECTIONS RELATION SCHEMA
/////////////////////////////////////////

export type ResourceCollectionsRelations = {
  community: CommunitiesWithRelations;
  resources: ResourcesWithRelations[];
};

export type ResourceCollectionsWithRelations = z.infer<typeof ResourceCollectionsSchema> & ResourceCollectionsRelations;

export const ResourceCollectionsWithRelationsSchema: z.ZodType<ResourceCollectionsWithRelations> = ResourceCollectionsSchema.merge(
  z.object({
    community: z.lazy(() => CommunitiesWithRelationsSchema),
    resources: z.lazy(() => ResourcesWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// RESOURCE COLLECTIONS OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type ResourceCollectionsOptionalDefaultsRelations = {
  community: CommunitiesOptionalDefaultsWithRelations;
  resources: ResourcesOptionalDefaultsWithRelations[];
};

export type ResourceCollectionsOptionalDefaultsWithRelations = z.infer<typeof ResourceCollectionsOptionalDefaultsSchema> &
  ResourceCollectionsOptionalDefaultsRelations;

export const ResourceCollectionsOptionalDefaultsWithRelationsSchema: z.ZodType<ResourceCollectionsOptionalDefaultsWithRelations> =
  ResourceCollectionsOptionalDefaultsSchema.merge(
    z.object({
      community: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema),
      resources: z.lazy(() => ResourcesOptionalDefaultsWithRelationsSchema).array(),
    }),
  );

/////////////////////////////////////////
// RESOURCE COLLECTIONS PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type ResourceCollectionsPartialRelations = {
  community?: CommunitiesPartialWithRelations;
  resources?: ResourcesPartialWithRelations[];
};

export type ResourceCollectionsPartialWithRelations = z.infer<typeof ResourceCollectionsPartialSchema> &
  ResourceCollectionsPartialRelations;

export const ResourceCollectionsPartialWithRelationsSchema: z.ZodType<ResourceCollectionsPartialWithRelations> =
  ResourceCollectionsPartialSchema.merge(
    z.object({
      community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
      resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
    }),
  ).partial();

export type ResourceCollectionsOptionalDefaultsWithPartialRelations = z.infer<typeof ResourceCollectionsOptionalDefaultsSchema> &
  ResourceCollectionsPartialRelations;

export const ResourceCollectionsOptionalDefaultsWithPartialRelationsSchema: z.ZodType<ResourceCollectionsOptionalDefaultsWithPartialRelations> =
  ResourceCollectionsOptionalDefaultsSchema.merge(
    z
      .object({
        community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
        resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type ResourceCollectionsWithPartialRelations = z.infer<typeof ResourceCollectionsSchema> & ResourceCollectionsPartialRelations;

export const ResourceCollectionsWithPartialRelationsSchema: z.ZodType<ResourceCollectionsWithPartialRelations> =
  ResourceCollectionsSchema.merge(
    z
      .object({
        community: z.lazy(() => CommunitiesPartialWithRelationsSchema),
        resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export default ResourceCollectionsSchema;

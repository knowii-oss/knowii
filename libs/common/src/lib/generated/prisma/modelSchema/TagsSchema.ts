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
// TAGS SCHEMA
/////////////////////////////////////////

export const TagsSchema = z.object({
  id: z.string().uuid(),
  name: z.string(),
  community_id: z.string().uuid(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type Tags = z.infer<typeof TagsSchema>;

/////////////////////////////////////////
// TAGS PARTIAL SCHEMA
/////////////////////////////////////////

export const TagsPartialSchema = TagsSchema.partial();

export type TagsPartial = z.infer<typeof TagsPartialSchema>;

/////////////////////////////////////////
// TAGS OPTIONAL DEFAULTS SCHEMA
/////////////////////////////////////////

export const TagsOptionalDefaultsSchema = TagsSchema.merge(
  z.object({
    id: z.string().uuid().optional(),
    created_at: z.coerce.date().optional(),
    updated_at: z.coerce.date().optional(),
  }),
);

export type TagsOptionalDefaults = z.infer<typeof TagsOptionalDefaultsSchema>;

/////////////////////////////////////////
// TAGS RELATION SCHEMA
/////////////////////////////////////////

export type TagsRelations = {
  community?: CommunitiesWithRelations | null;
  resources: ResourcesWithRelations[];
};

export type TagsWithRelations = z.infer<typeof TagsSchema> & TagsRelations;

export const TagsWithRelationsSchema: z.ZodType<TagsWithRelations> = TagsSchema.merge(
  z.object({
    community: z.lazy(() => CommunitiesWithRelationsSchema).nullable(),
    resources: z.lazy(() => ResourcesWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// TAGS OPTIONAL DEFAULTS RELATION SCHEMA
/////////////////////////////////////////

export type TagsOptionalDefaultsRelations = {
  community?: CommunitiesOptionalDefaultsWithRelations | null;
  resources: ResourcesOptionalDefaultsWithRelations[];
};

export type TagsOptionalDefaultsWithRelations = z.infer<typeof TagsOptionalDefaultsSchema> & TagsOptionalDefaultsRelations;

export const TagsOptionalDefaultsWithRelationsSchema: z.ZodType<TagsOptionalDefaultsWithRelations> = TagsOptionalDefaultsSchema.merge(
  z.object({
    community: z.lazy(() => CommunitiesOptionalDefaultsWithRelationsSchema).nullable(),
    resources: z.lazy(() => ResourcesOptionalDefaultsWithRelationsSchema).array(),
  }),
);

/////////////////////////////////////////
// TAGS PARTIAL RELATION SCHEMA
/////////////////////////////////////////

export type TagsPartialRelations = {
  community?: CommunitiesPartialWithRelations | null;
  resources?: ResourcesPartialWithRelations[];
};

export type TagsPartialWithRelations = z.infer<typeof TagsPartialSchema> & TagsPartialRelations;

export const TagsPartialWithRelationsSchema: z.ZodType<TagsPartialWithRelations> = TagsPartialSchema.merge(
  z.object({
    community: z.lazy(() => CommunitiesPartialWithRelationsSchema).nullable(),
    resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
  }),
).partial();

export type TagsOptionalDefaultsWithPartialRelations = z.infer<typeof TagsOptionalDefaultsSchema> & TagsPartialRelations;

export const TagsOptionalDefaultsWithPartialRelationsSchema: z.ZodType<TagsOptionalDefaultsWithPartialRelations> =
  TagsOptionalDefaultsSchema.merge(
    z
      .object({
        community: z.lazy(() => CommunitiesPartialWithRelationsSchema).nullable(),
        resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
      })
      .partial(),
  );

export type TagsWithPartialRelations = z.infer<typeof TagsSchema> & TagsPartialRelations;

export const TagsWithPartialRelationsSchema: z.ZodType<TagsWithPartialRelations> = TagsSchema.merge(
  z
    .object({
      community: z.lazy(() => CommunitiesPartialWithRelationsSchema).nullable(),
      resources: z.lazy(() => ResourcesPartialWithRelationsSchema).array(),
    })
    .partial(),
);

export default TagsSchema;

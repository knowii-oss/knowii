import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { resourceSchema } from './resource.schema';
import { resourceTextArticleSchema } from './resource-text-article.schema';
import { userProfileSchema } from './user.schema';
import { communityResourceCollectionSchema } from './community-resource-collection.schema';
import { slugSchema } from './slug.schema';

export const communityResourceSchema = baseEntitySchema.merge(slugSchema).merge(
  z.object({
    name: z.string(),
    description: z.string().nullable(),
    is_featured: z.boolean(),
    collection: communityResourceCollectionSchema,
    resource: resourceSchema,
    resource_text_article: resourceTextArticleSchema.nullable().optional(),
    curator: userProfileSchema,
  }),
);

export type CommunityResource = z.infer<typeof communityResourceSchema>;

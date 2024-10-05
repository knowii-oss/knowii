import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { resourceSchema } from './resource.schema';
import { resourceTextArticleSchema } from './resource-text-article.schema';
import { userProfileSchema } from './user.schema';
import { communityResourceCollectionSchema } from './community-resource-collection.schema';

export const communityResourceSchema = baseEntitySchema.merge(slugSchema).merge(
  z.object({
    is_featured: z.boolean(),
    collection: communityResourceCollectionSchema,
    resource: resourceSchema,
    textArticle: resourceTextArticleSchema.nullable(),
    curator: userProfileSchema,
  }),
);

export type CommunityResource = z.infer<typeof communityResourceSchema>;

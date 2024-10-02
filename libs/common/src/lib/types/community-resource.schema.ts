import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { resourceSchema } from './resource.schema';
import { resourceTextArticleSchema } from './resource-text-article.schema';
import { userProfileSchema } from './user.schema';

export const communityResourceSchema = baseEntitySchema.merge(
  z.object({
    is_featured: z.boolean(),
    resource: resourceSchema,
    textArticle: resourceTextArticleSchema.nullable(),
    curator: userProfileSchema,
  }),
);

export type CommunityResource = z.infer<typeof communityResourceSchema>;

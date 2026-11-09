import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { resourceSchema } from './resource.schema';
import { resourceTextArticleSchema } from './resource-text-article.schema';
import { userProfileSchema } from './user.schema';
import { spaceSchema } from './space.schema';
import { slugSchema } from './slug.schema';

export const communityResourceSchema = baseEntitySchema.extend(slugSchema.shape).extend({
  name: z.string(),
  description: z.string().nullable(),
  is_featured: z.boolean(),
  // The resources space that holds the resource (ADR 0015). Absent when the list is the space itself
  space: spaceSchema.optional(),
  resource: resourceSchema,
  resource_text_article: resourceTextArticleSchema.nullable().optional(),
  curator: userProfileSchema.nullable(),
});

export type CommunityResource = z.infer<typeof communityResourceSchema>;

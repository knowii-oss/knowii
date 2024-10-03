import { z } from 'zod';

import { singleItemApiResponseSchema } from '../single-item-api-response.schema';
import { resourceTextArticleSchema } from '../../types/resource-text-article.schema';
import { communityResourceSchema } from '../../types/community-resource.schema';

export const createResourceTextArticleResponseSchema = singleItemApiResponseSchema(
  communityResourceSchema.extend(
    z.object({
      textArticle: resourceTextArticleSchema.required(),
    }),
  ),
  z.object({}),
);

export type CreateResourceTextArticleResponse = z.infer<typeof createResourceTextArticleResponseSchema>;

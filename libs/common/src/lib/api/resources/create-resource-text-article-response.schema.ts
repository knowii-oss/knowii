import { z } from 'zod';

import { singleItemApiResponseSchema } from '../single-item-api-response.schema';
import { communityResourceSchema } from '../../types/community-resource.schema';

export const createResourceTextArticleResponseSchema = singleItemApiResponseSchema(communityResourceSchema, z.object({}));

export type CreateResourceTextArticleResponse = z.infer<typeof createResourceTextArticleResponseSchema>;

import { z } from 'zod';

import { communityResourceCollectionSchema } from '../../types/community-resource-collection.schema';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const createCommunityResourceCollectionResponseSchema = singleItemApiResponseSchema(communityResourceCollectionSchema, z.object({}));

export type CreateCommunityResourceCollectionResponse = z.infer<typeof createCommunityResourceCollectionResponseSchema>;

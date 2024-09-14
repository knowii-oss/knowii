import { z } from 'zod';

import { communitySchema } from '../../types/community.schema';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const createCommunityResponseSchema = singleItemApiResponseSchema(communitySchema, z.object({}));

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

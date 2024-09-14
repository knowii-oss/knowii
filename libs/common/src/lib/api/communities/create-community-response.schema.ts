import { z } from 'zod';

import { communitySchema } from '../../types/community.schema';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

const createCommunityResponseSchema = singleItemApiResponseSchema(communitySchema, z.object({}));

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

export const createCommunityInputSchema = communitySchema.pick({
  name: true,
  description: true,
  // TODO add slug
  //slug: true,
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

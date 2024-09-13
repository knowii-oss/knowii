import { z } from 'zod';

import { createApiResponseSchema } from '../api-response.schema';
import { communitySchema } from '../../types/community.schema';

const createCommunityResponseSchema = createApiResponseSchema(
  communitySchema.pick({
    name: true,
    description: true,
    //slug: true,
  }),
);

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

export const createCommunityInputSchema = communitySchema.pick({
  name: true,
  description: true,
  // TODO add slug
  //slug: true,
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

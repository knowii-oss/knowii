import { z } from 'zod';

import { communitySchema } from '../../types/community.schema';
import { emptyMetadataSchema, singleItemApiResponseSchema } from '../single-item-api-response.schema';

const createCommunityResponseSchema = singleItemApiResponseSchema(
  communitySchema.pick({
    cuid: true,
    name: true,
    description: true,
    personal: true,
    created_at: true,
    updated_at: true,
    // TODO add slug
    //slug: true,
  }), emptyMetadataSchema);

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

export const createCommunityInputSchema = communitySchema.pick({
  name: true,
  description: true,
  // TODO add slug
  //slug: true,
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

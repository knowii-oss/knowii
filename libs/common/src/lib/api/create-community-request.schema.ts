import { z } from 'zod';
import CommunitiesSchema from '../generated/prisma/modelSchema/CommunitiesSchema';
import { createApiResponseSchema } from './api-response.schema';

export const createCommunityRequestSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
});

export type CreateCommunityRequest = z.infer<typeof createCommunityRequestSchema>;

const createCommunityResponseSchema = createApiResponseSchema(
  CommunitiesSchema.pick({
    name: true,
    description: true,
    slug: true,
  }),
);

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

export const createCommunityInputSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
  slug: true,
}).extend({
  ownerUserId: z.string().uuid(),
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

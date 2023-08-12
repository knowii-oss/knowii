import { z } from 'zod';
import CommunitiesSchema from '../generated/prisma/modelSchema/CommunitiesSchema';

export const createCommunityRequestSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
});

export type CreateCommunityRequest = z.infer<typeof createCommunityRequestSchema>;

export const createCommunityResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.string().optional(),
  errorDetails: z.string().optional(),
  data: CommunitiesSchema.pick({
    id: true,
    name: true,
    description: true,
    slug: true,
  }).optional(),
});

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

export const createCommunityInputSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
  slug: true,
}).extend({
  ownerUserId: z.string().uuid(),
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

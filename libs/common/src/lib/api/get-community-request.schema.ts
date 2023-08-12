import { z } from 'zod';
import CommunitiesSchema from '../generated/prisma/modelSchema/CommunitiesSchema';

export const getCommunityRequestSchema = CommunitiesSchema.pick({
  slug: true,
});

export type GetCommunityRequest = z.infer<typeof getCommunityRequestSchema>;

export const getCommunityResponseDataSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
}).optional();

export type GetCommunityResponseData = z.infer<typeof getCommunityResponseDataSchema>;

export const getCommunityResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.string().optional(),
  errorDetails: z.string().optional(),
  data: getCommunityResponseDataSchema,
});

export type GetCommunityResponse = z.infer<typeof getCommunityResponseSchema>;

export const getCommunityInputSchema = CommunitiesSchema.pick({
  slug: true,
}).extend({
  userId: z.string().uuid().nullable(),
});

export type GetCommunityInput = z.infer<typeof getCommunityInputSchema>;

import { z } from 'zod';
import CommunitiesSchema from '../generated/prisma/modelSchema/CommunitiesSchema';
import { createApiResponseSchema } from './api-response.schema';

export const getCommunityRequestSchema = CommunitiesSchema.pick({
  slug: true,
});

export type GetCommunityRequest = z.infer<typeof getCommunityRequestSchema>;

export const getCommunityResponseDataSchema = CommunitiesSchema.pick({
  name: true,
  description: true,
}).optional();

export type GetCommunityResponseData = z.infer<typeof getCommunityResponseDataSchema>;

const getCommunityResponseSchema = createApiResponseSchema(getCommunityResponseDataSchema);

export type GetCommunityResponse = z.infer<typeof getCommunityResponseSchema>;

export const getCommunityInputSchema = CommunitiesSchema.pick({
  slug: true,
}).extend({
  userId: z.string().uuid().nullable(),
});

export type GetCommunityInput = z.infer<typeof getCommunityInputSchema>;

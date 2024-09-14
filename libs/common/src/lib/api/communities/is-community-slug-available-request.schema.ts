import { z } from 'zod';
import { allowedCommunitySlugCharactersRegex, maxLengthCommunitySlug, minLengthCommunitySlug } from '../../constants';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const isCommunitySlugAvailableRequestSchema = z.object({
  slugToCheck: z.string().regex(allowedCommunitySlugCharactersRegex).min(minLengthCommunitySlug).max(maxLengthCommunitySlug),
});

export type IsCommunitySlugAvailableRequest = z.infer<typeof isCommunitySlugAvailableRequestSchema>;

export const isCommunitySlugAvailableResponseDataSchema = z.object({
  isSlugAvailable: z.boolean(),
});

export type IsCommunitySlugAvailableResponseData = z.infer<typeof isCommunitySlugAvailableResponseDataSchema>;

export const isCommunitySlugAvailableResponseSchema = singleItemApiResponseSchema(isCommunitySlugAvailableResponseDataSchema, z.object({}));

export type IsCommunitySlugAvailableResponse = z.infer<typeof isCommunitySlugAvailableResponseSchema>;

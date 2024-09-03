import { z } from 'zod';
import { allowedCommunityNameCharactersRegex, maxLengthCommunityName, minLengthCommunityName } from '../constants-more';
import { createApiResponseSchema } from './api-response.schema';

export const isCommunityNameAvailableRequestSchema = z.object({
  nameToCheck: z.string().regex(allowedCommunityNameCharactersRegex).min(minLengthCommunityName).max(maxLengthCommunityName),
});

export type IsCommunityNameAvailableRequest = z.infer<typeof isCommunityNameAvailableRequestSchema>;

export const isCommunityNameAvailableResponseDataSchema = z.object({
  isNameAvailable: z.boolean(),
});

export type IsCommunityNameAvailableResponseData = z.infer<typeof isCommunityNameAvailableResponseDataSchema>;

export const isCommunityNameAvailableResponseSchema = createApiResponseSchema(isCommunityNameAvailableResponseDataSchema);

export type IsCommunityNameAvailableResponse = z.infer<typeof isCommunityNameAvailableResponseSchema>;

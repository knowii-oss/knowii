import { z } from 'zod';
import { allowedCommunityNameCharactersRegex, maxLengthCommunityName, minLengthCommunityName } from '../constants';

export const isCommunityNameAvailableRequestSchema = z.object({
  nameToCheck: z.string().regex(allowedCommunityNameCharactersRegex).min(minLengthCommunityName).max(maxLengthCommunityName),
});

export type IsCommunityNameAvailableRequest = z.infer<typeof isCommunityNameAvailableRequestSchema>;

export const isCommunityNameAvailableResponseSchema = z.object({
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  errorDetails: z.string().optional(),
  isNameAvailable: z.boolean().optional(),
});

export type IsCommunityNameAvailableResponse = z.infer<typeof isCommunityNameAvailableResponseSchema>;

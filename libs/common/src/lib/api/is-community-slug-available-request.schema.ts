import { z } from 'zod';
import { allowedCommunitySlugCharactersRegex, maxLengthCommunitySlug, minLengthCommunitySlug } from '../constants';

export const isCommunitySlugAvailableRequestSchema = z.object({
  slugToCheck: z.string().regex(allowedCommunitySlugCharactersRegex).min(minLengthCommunitySlug).max(maxLengthCommunitySlug),
});

export type IsCommunitySlugAvailableRequest = z.infer<typeof isCommunitySlugAvailableRequestSchema>;

export const isCommunitySlugAvailableResponseSchema = z.object({
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  errorDetails: z.string().optional(),
  isSlugAvailable: z.boolean().optional(),
});

export type IsCommunitySlugAvailableResponse = z.infer<typeof isCommunitySlugAvailableResponseSchema>;

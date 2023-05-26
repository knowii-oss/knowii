import { z } from 'zod';
import { allowedCommunityNameCharactersRegex, maxLengthCommunityName, minLengthCommunityName } from '../constants';

// FIXME improve with Prisma-zod generated types

// ----------------------------------------
// IS USERNAME AVAILABLE
// ----------------------------------------
export const isUsernameAvailableRequestSchema = z.object({
  usernameToCheck: z.string(),
});

export type IsUsernameAvailableRequest = z.infer<typeof isUsernameAvailableRequestSchema>;

export const isUsernameAvailableResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.optional(z.string()),
  isUsernameAvailable: z.optional(z.boolean()),
});

export type IsUsernameAvailableResponse = z.infer<typeof isUsernameAvailableResponseSchema>;

// ----------------------------------------
// IS COMMUNITY NAME AVAILABLE
// ----------------------------------------
export const isCommunityNameAvailableRequestSchema = z.object({
  nameToCheck: z.string(),
});

export type IsCommunityNameAvailableRequest = z.infer<typeof isCommunityNameAvailableRequestSchema>;

export const isCommunityNameAvailableResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.optional(z.string()),
  isNameAvailable: z.optional(z.boolean()),
});

export type IsCommunityNameAvailableResponse = z.infer<typeof isCommunityNameAvailableResponseSchema>;

// ----------------------------------------
// IS COMMUNITY SLUG AVAILABLE
// ----------------------------------------
export const isCommunitySlugAvailableRequestSchema = z.object({
  slugToCheck: z.string(),
});

export type IsCommunitySlugAvailableRequest = z.infer<typeof isCommunitySlugAvailableRequestSchema>;

export const isCommunitySlugAvailableResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.optional(z.string()),
  isSlugAvailable: z.optional(z.boolean()),
});

export type IsCommunitySlugAvailableResponse = z.infer<typeof isCommunitySlugAvailableResponseSchema>;

// ----------------------------------------
// CREATE COMMUNITY
// ----------------------------------------
export const createCommunityRequestSchema = z.object({
  name: z.string().min(minLengthCommunityName).max(maxLengthCommunityName).regex(allowedCommunityNameCharactersRegex),
  description: z.string(),
});

export type CreateCommunityRequest = z.infer<typeof createCommunityRequestSchema>;

export const createCommunityResponseSchema = z.object({
  error: z.optional(z.string()),
  errorDescription: z.optional(z.string()),
  data: z.optional(
    z.object({
      name: z.string(),
      description: z.string(),
      slug: z.string(),
    }),
  ),
});

export type CreateCommunityResponse = z.infer<typeof createCommunityResponseSchema>;

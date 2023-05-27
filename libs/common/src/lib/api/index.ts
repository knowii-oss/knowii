import { z } from 'zod';
import CommunitiesSchema from '../generated/prisma/modelSchema/CommunitiesSchema';

// FIXME improve with Prisma-zod generated types

// ----------------------------------------
// IS USERNAME AVAILABLE
// ----------------------------------------
export const isUsernameAvailableRequestSchema = z.object({
  usernameToCheck: z.string(),
});

export type IsUsernameAvailableRequest = z.infer<typeof isUsernameAvailableRequestSchema>;

export const isUsernameAvailableResponseSchema = z.object({
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  isUsernameAvailable: z.boolean().optional(),
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
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  isNameAvailable: z.boolean().optional(),
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
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  isSlugAvailable: z.boolean().optional(),
});

export type IsCommunitySlugAvailableResponse = z.infer<typeof isCommunitySlugAvailableResponseSchema>;

// ----------------------------------------
// CREATE COMMUNITY
// ----------------------------------------
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
});

export type CreateCommunityInput = z.infer<typeof createCommunityInputSchema>;

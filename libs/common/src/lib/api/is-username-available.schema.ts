import { z } from 'zod';
import { allowedUsernameCharactersRegex, maxLengthUsername, minLengthUsername } from '../constants';

export const isUsernameAvailableRequestSchema = z.object({
  usernameToCheck: z.string().regex(allowedUsernameCharactersRegex).min(minLengthUsername).max(maxLengthUsername),
});

export type IsUsernameAvailableRequest = z.infer<typeof isUsernameAvailableRequestSchema>;

export const isUsernameAvailableResponseSchema = z.object({
  error: z.string().optional(),
  errorDescription: z.string().optional(),
  errorDetails: z.string().optional(),
  isUsernameAvailable: z.boolean().optional(),
});

export type IsUsernameAvailableResponse = z.infer<typeof isUsernameAvailableResponseSchema>;

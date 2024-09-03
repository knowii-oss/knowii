import { z } from 'zod';
import { allowedUsernameCharactersRegex, maxLengthUsername, minLengthUsername } from '../constants-more';
import { createApiResponseSchema } from './api-response.schema';

export const isUsernameAvailableRequestSchema = z.object({
  usernameToCheck: z.string().regex(allowedUsernameCharactersRegex).min(minLengthUsername).max(maxLengthUsername),
});

export type IsUsernameAvailableRequest = z.infer<typeof isUsernameAvailableRequestSchema>;

export const isUsernameAvailableResponseDataSchema = z.object({
  isUsernameAvailable: z.boolean(),
});

export type IsUsernameAvailableResponseData = z.infer<typeof isUsernameAvailableResponseDataSchema>;

export const isUsernameAvailableResponseSchema = createApiResponseSchema(isUsernameAvailableResponseDataSchema);

export type IsUsernameAvailableResponse = z.infer<typeof isUsernameAvailableResponseSchema>;

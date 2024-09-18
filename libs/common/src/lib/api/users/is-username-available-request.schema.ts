import { z } from 'zod';
import { usernameSchema } from '../../types/username.schema';

export const isUsernameAvailableRequestSchema = z.object({
  usernameToCheck: usernameSchema,
});

export type IsUsernameAvailableRequest = z.infer<typeof isUsernameAvailableRequestSchema>;

import { z } from 'zod';
import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const isUsernameAvailableResponseSchema = singleItemApiResponseSchema(
  z.object({
    isUsernameAvailable: z.boolean(),
  }),
  z.object({}),
);

export type IsUsernameAvailableResponse = z.infer<typeof isUsernameAvailableResponseSchema>;

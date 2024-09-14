import { z } from 'zod';

import { singleItemApiResponseSchema } from '../single-item-api-response.schema';

export const pingResponseSchema = singleItemApiResponseSchema(
  z.object({
    pong: z.string(),
  }),
  z.object({}),
);

export type PingResponse = z.infer<typeof pingResponseSchema>;

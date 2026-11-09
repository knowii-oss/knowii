import { z } from 'zod';

export const urlSchema = z.object({
  url: z.url({
    error: `This should be a valid URL`,
  }),
});

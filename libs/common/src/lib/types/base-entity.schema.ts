import { z } from 'zod';

export const baseEntitySchema = z.object({
  cuid: z.string(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

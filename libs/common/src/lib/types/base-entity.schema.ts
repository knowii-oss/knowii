import { z } from 'zod';

export const baseEntitySchema = z.object({
  cuid: z.string().cuid2(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

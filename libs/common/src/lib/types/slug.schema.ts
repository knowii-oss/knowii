import { z } from 'zod';
import { MAX_LENGTH_SLUG } from '../constants';

export const slugSchema = z.object({
  slug: z.string().max(MAX_LENGTH_SLUG),
});

export type Slug = z.infer<typeof slugSchema>;

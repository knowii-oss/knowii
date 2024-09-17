import { z } from 'zod';
import { MAX_LENGTH_SLUG, MIN_LENGTH_SLUG } from '../constants';

export const slugSchema = z.object({
  slug: z.string().min(MIN_LENGTH_SLUG).max(MAX_LENGTH_SLUG),
});

export type Slug = z.infer<typeof slugSchema>;

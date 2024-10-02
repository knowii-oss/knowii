import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { slugSchema } from './slug.schema';

export const resourceTextArticleSchema = baseEntitySchema.merge(slugSchema).merge(
  z.object({
    content: z.string().optional(),
    word_count: z.number().int().optional(),
    reading_time: z.number().int().optional(),
  }),
);

export type ResourceTextArticle = z.infer<typeof resourceTextArticleSchema>;

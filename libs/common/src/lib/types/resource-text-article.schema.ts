import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION } from '../constants';
import { resourceLevelSchema } from './resource.schema';
import { urlSchema } from './url.schema';

// WARNING: those rules must remain aligned with those in CreateTextResource.php
export const newResourceTextArticleSchema = urlSchema.merge(
  z.object({
    description: z
      .string()
      .max(MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION, {
        message: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION} characters)`,
      })
      .optional(),
    level: resourceLevelSchema,
  }),
);

export type NewResourceTextArticle = z.infer<typeof newResourceTextArticleSchema>;

export const resourceTextArticleSchema = baseEntitySchema.merge(
  z.object({
    content: z.string().optional(),
    word_count: z.number().int().optional(),
    reading_time: z.number().int().optional(),
  }),
);

export type ResourceTextArticle = z.infer<typeof resourceTextArticleSchema>;

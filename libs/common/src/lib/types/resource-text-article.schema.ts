import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import {
  ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX,
  MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_RESOURCE_NAME,
  MIN_LENGTH_COMMUNITY_RESOURCE_NAME,
} from '../constants';
import { resourceLevelSchema } from './resource.schema';
import { urlSchema } from './url.schema';

// WARNING: those rules must remain aligned with those in CreateTextResource.php
export const newResourceTextArticleSchema = urlSchema.merge(
  z.object({
    name: z
      .string()
      .min(MIN_LENGTH_COMMUNITY_RESOURCE_NAME, {
        message: `Please choose a name for your new resource (between ${MIN_LENGTH_COMMUNITY_RESOURCE_NAME} and ${MAX_LENGTH_COMMUNITY_RESOURCE_NAME} characters)`,
      })
      .max(MAX_LENGTH_COMMUNITY_RESOURCE_NAME, {
        message: `The name is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_NAME} characters)`,
      })
      .regex(new RegExp(ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX), {
        message: 'Please use only letters, numbers, spaces, hyphens, or arobases',
      }),
    description: z
      .string()
      .max(MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION, {
        message: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION} characters)`,
      })
      .optional()
      .nullable(),
    level: resourceLevelSchema,
  }),
);

export type NewResourceTextArticle = z.infer<typeof newResourceTextArticleSchema>;

export const resourceTextArticleSchema = baseEntitySchema.merge(
  z.object({
    html: z.string().nullable(),
    markdown: z.string().nullable(),
    word_count: z.number().int().nullable(),
    reading_time: z.number().int().nullable(),
  }),
);

export type ResourceTextArticle = z.infer<typeof resourceTextArticleSchema>;

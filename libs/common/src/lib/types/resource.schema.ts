import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { slugSchema } from './slug.schema';

// Resource type
export const resourceTypeSchema = z.enum(['textArticle'], {
  message: 'Please select the type of resource',
});

export type ResourceType = z.infer<typeof resourceTypeSchema>;

export const resourceTypeSchemaOptions: Array<{ name: string; type: ResourceType }> = [
  {
    name: 'Text article',
    type: 'textArticle',
  },
];

// Resource level
export const resourceLevelSchema = z.enum(['beginner', 'intermediate', 'advanced', 'expert', 'unknown'], {
  message: 'Please select the level of the resource',
});

export type ResourceLevel = z.infer<typeof resourceLevelSchema>;

export const resourceLevelSchemaOptions: Array<{ name: string; level: ResourceLevel; icon: string }> = [
  { name: 'Unknown', level: 'unknown', icon: '❓' },
  { name: 'Beginner', level: 'beginner', icon: '🌱' },
  { name: 'Intermediate', level: 'intermediate', icon: '🌿' },
  { name: 'Advanced', level: 'advanced', icon: '🌳' },
  { name: 'Expert', level: 'expert', icon: '🏆' },
];

/**
 * Get the icon for a given resource level
 * @param level
 */
export const getIconForResourceLevel = (level: ResourceLevel): string => {
  let retVal = resourceLevelSchemaOptions.filter((option) => option.level === level)[0].icon;
  if (!retVal) {
    retVal = resourceLevelSchemaOptions[0].icon;
  }
  return retVal;
};

export const resourceSchema = baseEntitySchema.merge(slugSchema).merge(
  z.object({
    name: z.string(),
    description: z.string().nullable(),
    excerpt: z.string().nullable(),
    keywords: z.array(z.string()),
    ai_summary: z.string().nullable(),
    published_at: z.coerce.date().nullable(),
    modified_at: z.coerce.date().nullable(),
    language: z.string().nullable(),
    url: z.string().nullable(),
    cover_image: z.string().nullable(),
    cover_image_alt: z.string().nullable(),
    type: resourceTypeSchema,
    level: resourceLevelSchema,
    is_featured: z.boolean(),
    view_count: z.number().int().default(0),
    share_count: z.number().int().default(0),
    last_captured_at: z.coerce.date().nullable(),
    last_checked_at: z.coerce.date().nullable(),
    check_failures_count: z.number().int().default(0).nullable(),
    is_unavailable: z.boolean().nullable(),
  }),
);

export type Resource = z.infer<typeof resourceSchema>;

import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { slugSchema } from './slug.schema';

// Resource type
export const resourceTypeSchema = z.enum(['textArticle'], {
  error: 'Please select the type of resource',
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
  error: 'Please select the level of the resource',
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
  return (resourceLevelSchemaOptions.find((option) => option.level === level) ?? resourceLevelSchemaOptions[0])?.icon ?? '';
};

export const resourceSchema = baseEntitySchema.extend(slugSchema.shape).extend({
  name: z.string(),
  description: z.string().nullable(),
  excerpt: z.string().nullable(),
  keywords: z.array(z.string()),
  ai_summary: z.string().nullable(),
  published_at: z.coerce.date().nullable(),
  modified_at: z.coerce.date().nullable(),
  language: z.string().nullable(),
  url: z.string().nullable(),
  cover_image_alt: z.string().nullable(),
  // Stored by Knowii and served from its origin (#1063, ADR 0016). Lists carry them
  cover_image_thumbnail_url: z.string().nullable(),
  favicon_url: z.string().nullable(),
  // The type detected at capture; the community resource has the type that its community uses
  type: resourceTypeSchema,
  is_featured: z.boolean(),
  view_count: z.number().int().default(0),
  share_count: z.number().int().default(0),
  last_captured_at: z.coerce.date().nullable(),
  last_checked_at: z.coerce.date().nullable(),
  check_failures_count: z.number().int().default(0).nullable(),
  is_unavailable: z.boolean().nullable(),
});

export type Resource = z.infer<typeof resourceSchema>;

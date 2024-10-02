import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { slugSchema } from './slug.schema';

// Resource type
export const resourceTypeSchema = z.enum(['textArticle'], {
  message: 'Please select the type of resource',
});

export type ResourceType = z.infer<typeof resourceTypeSchema>;

export const resourceTypeSchemaOptions: Array<{ name: string; type: ResourceType }> = [{ name: 'Text article', type: 'textArticle' }];

// Resource level
export const resourceLevelSchema = z.enum(['beginner', 'intermediate', 'advanced', 'expert', 'unknown'], {
  message: 'Please select the level of the resource',
});

export type ResourceLevel = z.infer<typeof resourceLevelSchema>;

export const resourceLevelSchemaOptions: Array<{ name: string; level: ResourceLevel }> = [
  { name: 'Beginner', level: 'beginner' },
  { name: 'Intermediate', level: 'intermediate' },
  { name: 'Advanced', level: 'advanced' },
  { name: 'Expert', level: 'expert' },
  { name: 'Unknown', level: 'unknown' },
];

export const resourceSchema = baseEntitySchema.merge(slugSchema).merge(
  z.object({
    name: z.string(),
    description: z.string().optional(),
    excerpt: z.string().optional(),
    ai_summary: z.string().optional(),
    published_at: z.coerce.date().optional(),
    language: z.string().optional(),
    url: z.string().optional(),
    thumbnail_url: z.string().optional(),
    type: resourceTypeSchema,
    level: resourceLevelSchema,
    is_featured: z.boolean(),
    view_count: z.number().int().default(0),
    share_count: z.number().int().default(0),
    last_captured_at: z.coerce.date().optional(),
    last_checked_at: z.coerce.date().optional(),
    check_failures_count: z.number().int().default(0),
    is_unavailable: z.boolean(),
  }),
);

export type Resource = z.infer<typeof resourceSchema>;

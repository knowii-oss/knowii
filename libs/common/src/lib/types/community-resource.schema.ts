import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import { resourceLevelSchema, resourceSchema, resourceTypeSchema } from './resource.schema';
import { captureStatusSchema, resourceCaptureSchema } from './resource-capture.schema';
import { authorSchema } from './post.schema';
import { spaceSchema } from './space.schema';
import { slugSchema } from './slug.schema';

export const communityResourceSchema = baseEntitySchema.extend(slugSchema.shape).extend({
  name: z.string(),
  description: z.string().nullable(),
  is_featured: z.boolean(),
  // The resources space that holds the resource (ADR 0015). Absent when the list is the space itself
  space: spaceSchema.optional(),
  resource: resourceSchema,
  // ADR 0018: what the community decided, and the state of its capture
  type: resourceTypeSchema,
  level: resourceLevelSchema,
  capture_status: captureStatusSchema,
  capture_failure_reason: z.string().nullable(),
  // The captured content: the single resource only (lists never carry it); null when the page gave no readable content
  capture: resourceCaptureSchema.nullable().optional(),
  // Public summary of the member who added the resource (#1234). Null when the profile is deleted
  curator: authorSchema.nullable(),
});

export type CommunityResource = z.infer<typeof communityResourceSchema>;

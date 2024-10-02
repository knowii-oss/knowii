import { z } from 'zod';

import { MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION } from '../constants';
import { resourceLevelSchema } from './resource.schema';

// WARNING: those rules must remain aligned with those in CreateTextResource.php
export const newTextResourceSchema = z.object({
  url: z.string().url({
    message: `This should be a valid URL`,
  }),
  description: z
    .string()
    .max(MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION, {
      message: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION} characters)`,
    })
    .optional(),
  level: resourceLevelSchema,
});

export type NewTextResource = z.infer<typeof newTextResourceSchema>;

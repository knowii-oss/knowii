import { z } from 'zod';
import {
  ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX,
  MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_RESOURCE_NAME,
  MIN_LENGTH_COMMUNITY_RESOURCE_NAME,
} from '../constants';
import { resourceLevelSchema, resourceTypeSchema } from './resource.schema';
import { urlSchema } from './url.schema';

// The input to add a link to a resources space. WARNING: those rules must remain aligned with CreateTextResource.php
export const newCommunityResourceSchema = urlSchema.extend({
  // Optional: empty means "use the title of the page" (#1062)
  name: z.literal('').or(
    z
      .string()
      .min(MIN_LENGTH_COMMUNITY_RESOURCE_NAME, {
        error: `Please choose a name for your new resource (between ${MIN_LENGTH_COMMUNITY_RESOURCE_NAME} and ${MAX_LENGTH_COMMUNITY_RESOURCE_NAME} characters)`,
      })
      .max(MAX_LENGTH_COMMUNITY_RESOURCE_NAME, {
        error: `The name is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_NAME} characters)`,
      })
      .regex(new RegExp(ALLOWED_COMMUNITY_RESOURCE_NAME_CHARACTERS_REGEX), {
        error: 'Please use only letters, numbers, spaces, hyphens, or arobases',
      }),
  ),
  description: z
    .string()
    .max(MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION, {
      error: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_DESCRIPTION} characters)`,
    })
    .optional()
    .nullable(),
  level: resourceLevelSchema,
  // Empty: the capture detects the type (#1246)
  type: z.literal('').or(resourceTypeSchema).optional(),
});

// Change the type or the level of a resource (#1246)
export const communityResourceChangesSchema = z.object({
  type: resourceTypeSchema.optional(),
  level: resourceLevelSchema.optional(),
});

export type CommunityResourceChanges = z.infer<typeof communityResourceChangesSchema>;

export type NewCommunityResource = z.infer<typeof newCommunityResourceSchema>;

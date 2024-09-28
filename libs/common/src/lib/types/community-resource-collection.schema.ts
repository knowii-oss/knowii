import { z } from 'zod';
import { baseEntitySchema } from './base-entity.schema';
import {
  ALLOWED_COMMUNITY_RESOURCE_COLLECTION_NAME_CHARACTERS_REGEX,
  MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION,
  MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME,
  MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME,
} from '../constants';
import { slugSchema } from './slug.schema';

export const newCommunityResourceCollectionSchema = z.object({
  // WARNING: those rules must remain aligned with those in CreateCommunityResourceCollection.php
  name: z
    .string()
    .min(MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME, {
      message: `Please choose a name for your new resource collection (between ${MIN_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME} and ${MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME} characters)`,
    })
    .max(MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME, {
      message: `The name is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_NAME} characters)`,
    })
    .regex(new RegExp(ALLOWED_COMMUNITY_RESOURCE_COLLECTION_NAME_CHARACTERS_REGEX), {
      message: 'Please use only letters, numbers, spaces, hyphens, or arobases',
    }),
  description: z.string().max(MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION, {
    message: `Your description is too long (maximum ${MAX_LENGTH_COMMUNITY_RESOURCE_COLLECTION_DESCRIPTION} characters)`,
  }),
});

export type NewCommunityResourceCollection = z.infer<typeof newCommunityResourceCollectionSchema>;

export const communityResourceCollectionSchema = baseEntitySchema.merge(newCommunityResourceCollectionSchema).merge(slugSchema);

export type CommunityResourceCollection = z.infer<typeof communityResourceCollectionSchema>;

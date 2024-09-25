import { z } from 'zod';
import { newCommunityResourceCollectionSchema } from '../../types/community-resource-collection.schema';

export const createCommunityResourceCollectionRequestSchema = newCommunityResourceCollectionSchema;

export type CreateCommunityResourceCollectionRequest = z.infer<typeof createCommunityResourceCollectionRequestSchema>;

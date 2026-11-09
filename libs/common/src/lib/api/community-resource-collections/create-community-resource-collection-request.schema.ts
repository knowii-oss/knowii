import { z } from 'zod';
import { newCommunityResourceCollectionSchema } from '../../types/community-resource-collection.schema';

export const createCommunityResourceCollectionRequestSchema = newCommunityResourceCollectionSchema.extend({
  communityCuid: z.string().cuid2(),
});

export type CreateCommunityResourceCollectionRequest = z.infer<typeof createCommunityResourceCollectionRequestSchema>;

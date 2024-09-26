import { z } from 'zod';
import { newCommunityResourceCollectionSchema } from '../../types/community-resource-collection.schema';

export const createCommunityResourceCollectionRequestSchema = newCommunityResourceCollectionSchema.merge(
  z.object({
    communityCuid: z.string().cuid2(),
  }),
);

export type CreateCommunityResourceCollectionRequest = z.infer<typeof createCommunityResourceCollectionRequestSchema>;

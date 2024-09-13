import { z } from 'zod';

import { communitySchema } from '../../types/community.schema';

export const createCommunityRequestSchema = communitySchema.pick({
  name: true,
  description: true,
});

export type CreateCommunityRequest = z.infer<typeof createCommunityRequestSchema>;

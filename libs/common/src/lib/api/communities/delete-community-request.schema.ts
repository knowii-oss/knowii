import { z } from 'zod';
import { identifiableSchema } from '../../types/identifiable.schema';

export const deleteCommunityRequestSchema = z.object({
  community: identifiableSchema,
});

export type DeleteCommunityRequest = z.infer<typeof deleteCommunityRequestSchema>;

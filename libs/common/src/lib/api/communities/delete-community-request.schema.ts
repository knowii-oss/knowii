import { z } from 'zod';
import { identifiableSchema } from '../../types/identifiable.schema';

export const deleteCommunityRequestSchema = z.object({
  community: identifiableSchema,
  // The name of the community, as the member typed it: the server checks it again
  name: z.string(),
});

export type DeleteCommunityRequest = z.infer<typeof deleteCommunityRequestSchema>;

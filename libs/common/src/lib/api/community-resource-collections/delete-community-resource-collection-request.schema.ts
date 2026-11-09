import type { z } from 'zod';
import { identifiableSchema } from '../../types/identifiable.schema';
import { deleteCommunityRequestSchema } from '../communities/delete-community-request.schema';

export const deleteResourceCollectionRequestSchema = deleteCommunityRequestSchema.extend({
  resourceCollection: identifiableSchema,
});

export type DeleteResourceCollectionRequest = z.infer<typeof deleteResourceCollectionRequestSchema>;

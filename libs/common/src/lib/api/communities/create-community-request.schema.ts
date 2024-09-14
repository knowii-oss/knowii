import { z } from 'zod';
import { newCommunitySchema } from '../../types/community.schema';

export const createCommunityRequestSchema = newCommunitySchema;

export type CreateCommunityRequest = z.infer<typeof createCommunityRequestSchema>;

import { z } from 'zod';
import { identifiableSchema } from '../types/identifiable.schema';

export const deleteRequestSchema = identifiableSchema;

export type DeleteRequest = z.infer<typeof deleteRequestSchema>;

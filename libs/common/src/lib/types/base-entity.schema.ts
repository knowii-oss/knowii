import { z } from 'zod';
import { identifiableSchema } from './identifiable.schema';

export const baseEntitySchema = identifiableSchema.merge(
  z.object({
    created_at: z.coerce.date(),
    updated_at: z.coerce.date(),
  }),
);

export type BaseEntity = z.infer<typeof baseEntitySchema>;

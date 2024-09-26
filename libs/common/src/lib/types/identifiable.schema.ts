import { z } from 'zod';

export const identifiableSchema = z.object({
  cuid: z.string().cuid2(),
});

export type Identifiable = z.infer<typeof identifiableSchema>;

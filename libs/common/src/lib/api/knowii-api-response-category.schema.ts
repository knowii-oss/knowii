import { z } from 'zod';

export const knowiiApiResponseCategorySchema = z.enum(['business', 'security', 'technical']);

export type KnowiiApiResponseCategory = z.infer<typeof knowiiApiResponseCategorySchema>;
